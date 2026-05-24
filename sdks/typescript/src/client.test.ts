import { describe, it, expect, vi } from 'vitest';
import { ScanDeniedError, ScannerApiError, ScannerClient, ScannerSDKError, verifyWebhookSignature } from './index.js';
import { createHmac } from 'node:crypto';

function fakeFetch(impl: (url: string, init: RequestInit) => Promise<Response>): typeof fetch {
    return impl as unknown as typeof fetch;
}

function jsonResponse(body: unknown, status: number = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

describe('ScannerClient.pair', () => {
    it('POSTs to /pair, returns the token, and stores it on the client', async () => {
        const fetchMock = vi.fn(async (url: string, init: RequestInit) => {
            expect(url).toBe('https://app.test/api/v1/scanning/pair');
            expect(init.method).toBe('POST');
            const body = JSON.parse(init.body as string);
            expect(body.code).toBe('ABC23XYZ');
            return jsonResponse({
                token: 'scn_test_xyz',
                device: { uuid: 'd-1', label: 'iPhone', platform: 'ios' },
                profile: {
                    uuid: 'p-1',
                    name: 'Main',
                    capabilities: ['scan'],
                    allowed_event_ids: [],
                    max_scans_per_minute: 60,
                    duplicate_window_seconds: 10,
                },
            });
        });

        const sdk = new ScannerClient({ baseUrl: 'https://app.test', fetchImpl: fakeFetch(fetchMock) });
        const res = await sdk.pair({ code: 'ABC23XYZ', device_label: 'iPhone' });

        expect(res.token).toBe('scn_test_xyz');
        expect(sdk.currentToken).toBe('scn_test_xyz');
        expect(fetchMock).toHaveBeenCalledOnce();
    });

    it('omits the Authorization header on pair', async () => {
        const fetchMock = vi.fn(async (_url: string, init: RequestInit) => {
            const headers = (init.headers ?? {}) as Record<string, string>;
            expect(headers['Authorization']).toBeUndefined();
            return jsonResponse({ token: 't', device: {}, profile: {} });
        });
        const sdk = new ScannerClient({ baseUrl: 'https://app.test', fetchImpl: fakeFetch(fetchMock) });
        await sdk.pair({ code: 'X', device_label: 'd' });
    });
});

describe('ScannerClient.scan', () => {
    function clientWithResponse(body: unknown, status: number = 200): ScannerClient {
        const fetchMock = vi.fn(async () => jsonResponse(body, status));
        return new ScannerClient({
            baseUrl: 'https://app.test',
            token: 'scn_test',
            fetchImpl: fakeFetch(fetchMock),
        });
    }

    it('returns the scan result on verdict=allow', async () => {
        const sdk = clientWithResponse({
            scan_uuid: 's-1',
            verdict: 'allow',
            reason_code: null,
            was_admitted: true,
            was_duplicate: false,
            was_voided: false,
            flags: [],
            latency_ms: 12,
            ticket: null,
            event: null,
            server_time: null,
        });
        const r = await sdk.scan({ payload: 'TKT-1' });
        expect(r.verdict).toBe('allow');
        expect(r.was_admitted).toBe(true);
    });

    it('throws ScanDeniedError on verdict=deny by default', async () => {
        const sdk = clientWithResponse(
            {
                scan_uuid: 's-2',
                verdict: 'deny',
                reason_code: 'voided_ticket',
                was_admitted: false,
                was_duplicate: false,
                was_voided: true,
                flags: [{ rule: 'voided_ticket', outcome: 'deny', severity: 7, reason_code: 'voided_ticket', message: 'voided' }],
                latency_ms: 4,
                ticket: null,
                event: null,
                server_time: null,
            },
            409,
        );

        await expect(sdk.scan({ payload: 'TKT-1' })).rejects.toBeInstanceOf(ScanDeniedError);
    });

    it('returns the deny payload when throwOnDeny is disabled', async () => {
        const fetchMock = vi.fn(async () =>
            jsonResponse(
                {
                    scan_uuid: 's-3',
                    verdict: 'deny',
                    reason_code: 'wrong_event',
                    was_admitted: false,
                    was_duplicate: false,
                    was_voided: false,
                    flags: [],
                    latency_ms: 4,
                    ticket: null,
                    event: null,
                    server_time: null,
                },
                409,
            ),
        );
        const sdk = new ScannerClient({
            baseUrl: 'https://app.test',
            token: 'scn_test',
            fetchImpl: fakeFetch(fetchMock),
            throwOnDeny: false,
        });

        const r = await sdk.scan({ payload: 'X' });
        expect(r.verdict).toBe('deny');
        expect(r.reason_code).toBe('wrong_event');
    });
});

describe('ScannerClient — auth + errors', () => {
    it('throws when calling a protected method without a token', async () => {
        const sdk = new ScannerClient({ baseUrl: 'https://app.test' });
        await expect(sdk.me()).rejects.toBeInstanceOf(ScannerSDKError);
    });

    it('maps a 401 response to ScannerApiError with the server error code', async () => {
        const fetchMock = vi.fn(async () => jsonResponse({ error: 'invalid_token' }, 401));
        const sdk = new ScannerClient({
            baseUrl: 'https://app.test',
            token: 'scn_test',
            fetchImpl: fakeFetch(fetchMock),
        });

        try {
            await sdk.me();
            throw new Error('should not reach here');
        } catch (e) {
            expect(e).toBeInstanceOf(ScannerApiError);
            expect((e as ScannerApiError).apiError.code).toBe(401);
            expect((e as ScannerApiError).apiError.error).toBe('invalid_token');
        }
    });
});

describe('ScannerClient.batchScan', () => {
    it('rejects empty batches up front', async () => {
        const sdk = new ScannerClient({ baseUrl: 'https://app.test', token: 't' });
        await expect(sdk.batchScan([])).rejects.toBeInstanceOf(ScannerSDKError);
    });

    it('rejects > 100 batches up front', async () => {
        const sdk = new ScannerClient({ baseUrl: 'https://app.test', token: 't' });
        const tooMany = new Array(101).fill({ payload: 'x' });
        await expect(sdk.batchScan(tooMany)).rejects.toBeInstanceOf(ScannerSDKError);
    });
});

describe('verifyWebhookSignature', () => {
    const secret = 'whsec_test_xyz';
    const body = JSON.stringify({ scan: { uuid: 's-1' } });

    function makeHeader(unix: number, sig: string): string {
        return `t=${unix},v1=${sig}`;
    }

    it('returns true for a valid signature within tolerance', () => {
        const now = Math.floor(Date.now() / 1000);
        const sig = createHmac('sha256', secret).update(`${now}.${body}`).digest('hex');
        expect(verifyWebhookSignature(body, makeHeader(now, sig), secret)).toBe(true);
    });

    it('returns false for an old timestamp outside tolerance', () => {
        const old = Math.floor(Date.now() / 1000) - 999;
        const sig = createHmac('sha256', secret).update(`${old}.${body}`).digest('hex');
        expect(verifyWebhookSignature(body, makeHeader(old, sig), secret, 300)).toBe(false);
    });

    it('returns false for a bad signature', () => {
        const now = Math.floor(Date.now() / 1000);
        const wrong = '0'.repeat(64);
        expect(verifyWebhookSignature(body, makeHeader(now, wrong), secret)).toBe(false);
    });

    it('throws when the header is missing', () => {
        expect(() => verifyWebhookSignature(body, undefined, secret)).toThrow();
    });

    it('throws when the header is malformed', () => {
        expect(() => verifyWebhookSignature(body, 'garbage', secret)).toThrow();
    });
});
