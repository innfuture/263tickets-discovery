<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Distribution network
|--------------------------------------------------------------------------
|
| Tunables for the physical-ticket distribution network. Most values
| should rarely change after launch; the signing_secret MUST be set
| in production or dispatch manifests will be issued unsigned.
|
*/

return [

    // Shared HMAC secret used by ManifestSigner. Blank = unsigned
    // dispatches (acceptable for local dev / CI; refuse in production).
    'manifest_signing_secret' => env('DISTRIBUTION_MANIFEST_SECRET', ''),

    // Default tamper-tolerance window for dispatch signature t= field.
    'manifest_signature_tolerance_seconds' => (int) env('DISTRIBUTION_SIG_TOLERANCE', 86400),

    'dispatch' => [
        // Maximum tickets in a single dispatch. Larger dispatches are
        // possible by splitting; cap exists so a fat-finger doesn't
        // ship an entire event to one distributor by accident.
        'max_tickets_per_dispatch' => (int) env('DISTRIBUTION_MAX_TICKETS_PER_DISPATCH', 100000),

        // Default time-in-transit before a dispatch is escalated as
        // "stuck" in the backoffice dashboard.
        'transit_sla_hours' => (int) env('DISTRIBUTION_TRANSIT_SLA_HOURS', 72),
    ],

    'voiding' => [
        // Voiding more than this many tickets in a 24h window requires
        // a second approver (dual-control). Tuned to be generous for
        // normal returns; tight enough to catch a compromised account.
        'dual_control_threshold' => (int) env('DISTRIBUTION_DUAL_CONTROL_THRESHOLD', 50),

        // Rolling window for the dual-control rate counter.
        'dual_control_window_hours' => (int) env('DISTRIBUTION_DUAL_CONTROL_WINDOW_HOURS', 24),
    ],

    'sales' => [
        // Velocity guard: more than this many sales per device per
        // minute trips a hard reject + ledger flag.
        'max_sales_per_device_per_minute' => (int) env('DISTRIBUTION_SALES_VELOCITY_LIMIT', 20),

        // Geofence anomaly = soft signal (ledger entry + dashboard
        // flag), not a hard block. Toggle to hard-block in markets
        // where door-to-door sales aren't a thing.
        'geofence_hard_block' => filter_var(env('DISTRIBUTION_GEOFENCE_HARD_BLOCK', false), FILTER_VALIDATE_BOOLEAN),

        // Time-window: tickets are only sellable from N days before
        // their event until end-of-event. 0 = no limit.
        'sale_window_days_before_event' => (int) env('DISTRIBUTION_SALE_WINDOW_DAYS', 0),
    ],

    'spot_audit' => [
        // Daily challenge size (per active distributor).
        'tickets_per_challenge' => (int) env('DISTRIBUTION_SPOT_AUDIT_SIZE', 3),

        // Response window before the challenge auto-expires.
        'response_window_hours' => (int) env('DISTRIBUTION_SPOT_AUDIT_HOURS', 24),

        // Distributors whose audit-fail rate exceeds this fraction
        // over the rolling window lose dispatch privileges.
        'fail_rate_threshold' => (float) env('DISTRIBUTION_SPOT_AUDIT_FAIL_RATE', 0.15),
    ],

    'trust_score' => [
        // Composite scoring weights. Each component contributes a
        // 0-1 value which is summed against the weights below. Score
        // is then mapped to 0-100 and stored on distributors.trust_score.
        'weights' => [
            'dispatch_receipt_latency' => 0.15,
            'sales_velocity_vs_peers' => 0.10,
            'geo_anomaly_rate' => 0.20,
            'spot_audit_pass_rate' => 0.30,
            'leakage_rate' => 0.25,
        ],
    ],

    'edge' => [
        // Push activation entries to scanner-edge KV. Off until the
        // edge worker is configured.
        'push_activations_to_edge' => filter_var(env('DISTRIBUTION_PUSH_ACTIVATIONS', false), FILTER_VALIDATE_BOOLEAN),

        // Only push activations for events starting within the next
        // N hours. Keeps the edge KV size bounded for orgs with a
        // long-tail of legacy tickets.
        'activation_horizon_hours' => (int) env('DISTRIBUTION_ACTIVATION_HORIZON_HOURS', 48),
    ],
];
