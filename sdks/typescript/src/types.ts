/**
 * Mirrors the wire shapes documented in app/Http/Controllers/Api/Scanning/*.
 * Keep these in sync with the PHP controllers — the SDK serialises and
 * deserialises but never invents its own field names.
 */

export type ScanVerdict = 'allow' | 'warn' | 'deny';

export type FraudFlag = {
    rule: string;
    outcome: ScanVerdict;
    severity: number;
    reason_code: string | null;
    message: string | null;
};

export type BiometricAttestation = {
    algorithm: string;
    confidence: number;
    nonce: string;
    signature: string;
};

export type PairRequest = {
    code: string;
    device_label: string;
    platform?: 'ios' | 'android' | 'web' | 'other';
    app_version?: string;
    hardware_id?: string;
};

export type Profile = {
    uuid: string;
    name: string;
    capabilities: string[];
    allowed_event_ids: number[];
    max_scans_per_minute: number;
    duplicate_window_seconds: number;
};

export type Device = {
    uuid: string;
    label: string;
    platform: string;
};

export type PairResponse = {
    token: string;
    device: Device;
    profile: Profile;
};

export type ScanRequest = {
    payload: string;
    client_lat?: number | null;
    client_lng?: number | null;
    client_at?: string;
    device_meta?: Record<string, unknown>;
    biometric?: BiometricAttestation;
};

export type TicketSummary = {
    uuid: string;
    ticket_number: string;
    scan_count: number;
    admission_type: string | null;
    pass_type: string | null;
    is_voided: boolean;
};

export type EventRef = {
    id: number;
    slug: string;
    name: string;
};

export type ScanResult = {
    scan_uuid: string;
    verdict: ScanVerdict;
    reason_code: string | null;
    was_admitted: boolean;
    was_duplicate: boolean;
    was_voided: boolean;
    flags: FraudFlag[];
    latency_ms: number;
    ticket: TicketSummary | null;
    event: EventRef | null;
    server_time: string | null;
};

export type BatchScanResult = {
    results: Array<ScanResult | { verdict: 'deny'; reason_code: string; message: string; payload: string | null }>;
};

export type MeResponse = {
    device: Device & { last_seen_at: string | null };
    profile: Profile;
    events: Array<{
        id: number;
        slug: string;
        name: string;
        starts_at: string | null;
        ends_at: string | null;
        venue: string | null;
    }>;
    server_time: string;
};

export type HeartbeatRequest = {
    lat?: number;
    lng?: number;
    battery?: number;
    connection?: string;
};

export type HeartbeatResponse = {
    ok: boolean;
    revoked: boolean;
    server_time: string;
};

export type LookupResponse = {
    verdict: ScanVerdict;
    reason_code: string | null;
    flags: FraudFlag[];
    ticket: (TicketSummary & { first_scanned_at: string | null }) | null;
    event: EventRef & { starts_at: string | null } | null;
};

export type EventAnalytics = {
    event: EventRef;
    counters: {
        issued: number;
        scanned: number;
        voided: number;
        scan_rate_pct: number;
    };
    verdicts_24h: { allow: number; warn: number; deny: number };
    velocity_60m: Array<{ minute: string; admitted: number }>;
    top_denials_24h: Array<{ reason_code: string; count: number }>;
    server_time: string;
};

export type RecentScansResponse = {
    scans: Array<{
        uuid: string;
        payload: string;
        verdict: ScanVerdict;
        reason_code: string | null;
        was_admitted: boolean;
        event_id: number | null;
        created_at: string | null;
        flags: FraudFlag[];
    }>;
};

export type ApiError = {
    code: number;
    error: string;
    message?: string;
    raw?: unknown;
};
