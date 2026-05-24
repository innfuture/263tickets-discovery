import type { ApiError, FraudFlag, ScanVerdict } from './types.js';

/** Base class — all SDK throws inherit from this. */
export class ScannerSDKError extends Error {
    public readonly code: number;
    public readonly raw?: unknown;

    constructor(message: string, code: number = 0, raw?: unknown) {
        super(message);
        this.name = 'ScannerSDKError';
        this.code = code;
        this.raw = raw;
    }
}

/** Thrown when the server returns a non-2xx HTTP status. */
export class ScannerApiError extends ScannerSDKError {
    public readonly apiError: ApiError;

    constructor(apiError: ApiError) {
        super(apiError.message ?? apiError.error, apiError.code, apiError.raw);
        this.name = 'ScannerApiError';
        this.apiError = apiError;
    }
}

/** Thrown when a single scan came back with `verdict: deny`. */
export class ScanDeniedError extends ScannerSDKError {
    public readonly verdict: ScanVerdict;
    public readonly reasonCode: string | null;
    public readonly flags: FraudFlag[];

    constructor(reasonCode: string | null, flags: FraudFlag[], message?: string) {
        super(message ?? reasonCode ?? 'Scan denied', 409, { reasonCode, flags });
        this.name = 'ScanDeniedError';
        this.verdict = 'deny';
        this.reasonCode = reasonCode;
        this.flags = flags;
    }
}
