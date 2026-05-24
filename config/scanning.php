<?php

use App\Services\Scanning\Rules\BiometricVerificationRule;
use App\Services\Scanning\Rules\DuplicateScanRule;
use App\Services\Scanning\Rules\GeofenceRule;
use App\Services\Scanning\Rules\OffPeakRule;
use App\Services\Scanning\Rules\TicketNotFoundRule;
use App\Services\Scanning\Rules\VelocityRule;
use App\Services\Scanning\Rules\VoidedTicketRule;
use App\Services\Scanning\Rules\WrongEventRule;
use App\Services\Scanning\StubBiometricProvider;

/*
|--------------------------------------------------------------------------
| Scanner / third-party scanning configuration
|--------------------------------------------------------------------------
|
| Controls the public API at /api/v1/scanning/* and the pluggable
| fraud-rule pipeline that evaluates every scan.
|
| The fraud_rules list is order-independent — the FraudEngine combines
| all verdicts and picks the strongest (deny > warn > allow). Drop a
| class into the list to add a custom rule; remove one to drop it from
| the pipeline. No FraudEngine edits required.
|
*/

return [

    'pairing' => [
        // How long an organizer-generated pairing code remains valid
        // before the device must request a new one. Short enough to
        // limit replay risk if a code is overheard.
        'code_ttl_minutes' => (int) env('SCANNER_PAIRING_TTL_MINUTES', 30),
    ],

    'auth' => [
        // Token prefix shown to users — helps them identify which
        // string is the bearer secret in logs and clipboard.
        'token_display_prefix' => 'scn_',

        // Default rate limit when a profile doesn't override it.
        'default_max_per_minute' => 60,

        // Default duplicate window — re-scans of the same ticket
        // within this many seconds trigger the duplicate rule.
        'default_duplicate_window_seconds' => 10,
    ],

    'geofence_radius_km' => (float) env('SCANNER_GEOFENCE_RADIUS_KM', 5.0),

    'off_peak_grace_minutes' => (int) env('SCANNER_OFF_PEAK_GRACE_MINUTES', 60),

    // Biometric — opt an event in by setting
    // `events.metadata.biometric_required=true`. Swap the provider
    // class to ship a vendor SDK implementation; StubBiometricProvider
    // is the dev / sandbox default.
    'biometric_provider' => env('SCANNING_BIOMETRIC_PROVIDER', StubBiometricProvider::class),
    'biometric_default_threshold' => (float) env('SCANNING_BIOMETRIC_THRESHOLD', 0.85),
    'biometric_signing_secret' => env('SCANNING_BIOMETRIC_SECRET'),

    // Order doesn't matter — the engine combines all results. Listing
    // the heavy-DB rules later than the cheap pure-PHP ones helps when
    // a deny short-circuits in the future.
    'fraud_rules' => [
        TicketNotFoundRule::class,
        VoidedTicketRule::class,
        WrongEventRule::class,
        OffPeakRule::class,
        GeofenceRule::class,
        DuplicateScanRule::class,
        VelocityRule::class,
        BiometricVerificationRule::class,
    ],

    // NFC ticketing — mobile wallet → reader tap verification.
    // `nfc_providers` maps the `provider` request param to the
    // implementation class. Add an entry to plug in a vendor-specific
    // reader SDK driver.
    'nfc_providers' => [
        'stub' => \App\Services\Scanning\Nfc\StubNfcProvider::class,
        'apple_vas' => \App\Services\Scanning\Nfc\AppleVasProvider::class,
        'google_smart_tap' => \App\Services\Scanning\Nfc\GoogleSmartTapProvider::class,
    ],

    'nfc' => [
        'apple_vas' => [
            // Apple VAS merchant identifier (configured in the
            // Apple Developer portal alongside your Pass Type ID).
            'merchant_id' => env('SCANNING_APPLE_VAS_MERCHANT_ID'),
            'cert_path' => env('SCANNING_APPLE_VAS_CERT_PATH'),
            'cert_passphrase' => env('SCANNING_APPLE_VAS_CERT_PASSPHRASE', ''),
        ],
        'google_smart_tap' => [
            'issuer_id' => env('SCANNING_GOOGLE_SMART_TAP_ISSUER_ID'),
            'key_path' => env('SCANNING_GOOGLE_SMART_TAP_KEY_PATH'),
        ],
    ],

    'webhooks' => [
        // Retry policy for the outbound scan webhook (per-profile URL).
        // Identical shape to the payment webhook backoff to keep the
        // operational mental model consistent.
        'retries' => [1, 5, 30, 300, 1800],
        'timeout_seconds' => 5,
    ],

    'edge' => [
        // Cloudflare KV bridge for the scanner-edge worker. When all
        // three values are present, the PushVoidedTicketToEdgeJob
        // upserts voided ticket UUIDs into the KV namespace so the
        // edge can deny scans without an origin round-trip.
        'kv' => [
            'account_id' => env('SCANNING_EDGE_KV_ACCOUNT_ID'),
            'namespace_id' => env('SCANNING_EDGE_KV_NAMESPACE_ID'),
            'api_token' => env('SCANNING_EDGE_KV_API_TOKEN'),
        ],
    ],

];
