import { ScanDeniedError, ScannerApiError, ScannerSDKError } from './errors.js';
import type {
    BatchScanResult,
    EventAnalytics,
    HeartbeatRequest,
    HeartbeatResponse,
    LookupResponse,
    MeResponse,
    PairRequest,
    PairResponse,
    RecentScansResponse,
    ScanRequest,
    ScanResult,
} from './types.js';

export interface ScannerClientOptions {
    /** Base URL — e.g. https://app.example.com. No trailing slash. */
    baseUrl: string;

    /** Bearer token from `pair()`. Omit for the pair() call itself. */
    token?: string;

    /** Optional override (mostly for tests). Defaults to global fetch. */
    fetchImpl?: typeof fetch;

    /** Per-request timeout. Defaults to 10 s. */
    timeoutMs?: number;

    /** Throw on verdict=deny in single scans. Default true. */
    throwOnDeny?: boolean;
}

export class ScannerClient {
    private readonly baseUrl: string;
    private token: string | undefined;
    private readonly fetchImpl: typeof fetch;
    private readonly timeoutMs: number;
    private readonly throwOnDeny: boolean;

    constructor(opts: ScannerClientOptions) {
        this.baseUrl = opts.baseUrl.replace(/\/+$/, '');
        this.token = opts.token;
        this.fetchImpl = opts.fetchImpl ?? fetch.bind(globalThis);
        this.timeoutMs = opts.timeoutMs ?? 10_000;
        this.throwOnDeny = opts.throwOnDeny ?? true;
    }

    /** Read the current token; useful for persisting after pair(). */
    get currentToken(): string | undefined {
        return this.token;
    }

    /**
     * Pair this device against an organizer-issued code. Updates the
     * client's stored token on success.
     *
     *   const sdk = new ScannerClient({ baseUrl });
     *   const { token, profile } = await sdk.pair({ code, device_label });
     *   // Persist `token` in the OS keychain — never log it.
     */
    async pair(req: PairRequest): Promise<PairResponse> {
        const response = await this.request<PairResponse>('POST', '/api/v1/scanning/pair', req, /*requiresAuth*/ false);
        this.token = response.token;
        return response;
    }

    /** Returns the active profile, capabilities, and scoped events. */
    me(): Promise<MeResponse> {
        return this.request('GET', '/api/v1/scanning/me');
    }

    /**
     * Submit a single scan. The server returns HTTP 200 on allow/warn
     * and HTTP 409 on deny — both carry the same `ScanResult` body.
     * The SDK normalises that so callers never have to special-case
     * HTTP status: a deny becomes either a `ScanResult` with
     * `verdict: 'deny'` (when `throwOnDeny: false`) or a thrown
     * `ScanDeniedError` (default).
     */
    async scan(req: ScanRequest): Promise<ScanResult> {
        let result: ScanResult;
        try {
            result = await this.request<ScanResult>('POST', '/api/v1/scanning/scan', req);
        } catch (e) {
            // 409 carries a ScanResult body; unwrap it so the caller
            // can react to verdict + flags rather than HTTP semantics.
            if (e instanceof ScannerApiError && e.apiError.code === 409 && isScanResultShape(e.apiError.raw)) {
                result = e.apiError.raw as ScanResult;
            } else {
                throw e;
            }
        }

        if (this.throwOnDeny && result.verdict === 'deny') {
            throw new ScanDeniedError(result.reason_code, result.flags, result.reason_code ?? undefined);
        }

        return result;
    }

    /**
     * Submit a batch of up to 100 scans (offline-sync path). Validation
     * errors come back as rejected promises so callers can `await`
     * uniformly.
     */
    async batchScan(scans: ScanRequest[]): Promise<BatchScanResult> {
        if (scans.length === 0) throw new ScannerSDKError('At least one scan is required.', 422);
        if (scans.length > 100) throw new ScannerSDKError('Batch cannot exceed 100 scans.', 422);
        return this.request('POST', '/api/v1/scanning/scan/batch', { scans });
    }

    /** Verify-only lookup. Does not increment scan_count. */
    lookup(payload: string): Promise<LookupResponse> {
        return this.request('GET', `/api/v1/scanning/tickets/${encodeURIComponent(payload)}`);
    }

    /** Heartbeat + telemetry. Returns `revoked` so the app can drop its token. */
    heartbeat(req: HeartbeatRequest = {}): Promise<HeartbeatResponse> {
        return this.request('POST', '/api/v1/scanning/heartbeat', req);
    }

    /** Entry analytics for an event the device can see. */
    eventAnalytics(eventSlug: string): Promise<EventAnalytics> {
        return this.request('GET', `/api/v1/scanning/events/${encodeURIComponent(eventSlug)}/analytics`);
    }

    /** Recent scans across the profile. */
    recent(): Promise<RecentScansResponse> {
        return this.request('GET', '/api/v1/scanning/recent');
    }

    private async request<T>(
        method: string,
        path: string,
        body?: unknown,
        requiresAuth: boolean = true,
    ): Promise<T> {
        if (requiresAuth && !this.token) {
            throw new ScannerSDKError('No token — call pair() first or pass `token` to the constructor.', 401);
        }

        const headers: Record<string, string> = {
            Accept: 'application/json',
            'User-Agent': '@example-app/scanner-sdk/1.0',
        };
        if (body !== undefined) headers['Content-Type'] = 'application/json';
        if (requiresAuth && this.token) headers['Authorization'] = `Bearer ${this.token}`;

        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), this.timeoutMs);

        let res: Response;
        try {
            res = await this.fetchImpl(`${this.baseUrl}${path}`, {
                method,
                headers,
                body: body !== undefined ? JSON.stringify(body) : undefined,
                signal: controller.signal,
            });
        } catch (e) {
            throw new ScannerSDKError(`Network error: ${(e as Error).message}`, 0, e);
        } finally {
            clearTimeout(timer);
        }

        const raw = await res.text();
        const parsed: unknown = raw ? safeJson(raw) : null;

        if (!res.ok) {
            const apiErr = (parsed && typeof parsed === 'object' && parsed !== null)
                ? (parsed as Record<string, unknown>)
                : { error: 'http_error', message: `HTTP ${res.status}` };
            throw new ScannerApiError({
                code: res.status,
                error: String(apiErr['error'] ?? 'http_error'),
                message: typeof apiErr['message'] === 'string' ? apiErr['message'] : undefined,
                raw: parsed,
            });
        }

        return parsed as T;
    }
}

function safeJson(raw: string): unknown {
    try {
        return JSON.parse(raw);
    } catch {
        return null;
    }
}

/**
 * Best-effort duck check: the deny path's HTTP 409 body should look
 * like a ScanResult. We avoid a strict schema validator to keep the
 * SDK dependency-free.
 */
function isScanResultShape(v: unknown): boolean {
    if (v === null || typeof v !== 'object') return false;
    const o = v as Record<string, unknown>;
    return typeof o['verdict'] === 'string' && 'scan_uuid' in o && 'flags' in o;
}
