<?php

use App\Services\Payments\Drivers\EcoCashGateway;
use App\Services\Payments\Drivers\PaynowGateway;
use App\Services\Payments\Drivers\PesepayGateway;
use App\Services\Payments\Drivers\SandboxGateway;
use App\Services\Payments\Drivers\StripeGateway;
use App\Services\Payments\Drivers\ZimswitchGateway;

/*
|--------------------------------------------------------------------------
| Payment Gateway Configuration
|--------------------------------------------------------------------------
|
| Each gateway driver reads its credentials from the array below. Drivers
| are resolved via the PaymentManager (`Payments::gateway('paynow')`) and
| can be enabled / disabled independently. The `default` key controls the
| fallback gateway when the caller does not specify one.
|
| Credentials are platform-wide for this deployment. If you later need to
| support per-organization merchant accounts, swap the read site inside
| each driver's `config()` accessor — the public contract does not change.
|
*/

return [

    'default' => env('PAYMENTS_DEFAULT_GATEWAY', 'paynow'),

    'currency' => env('PAYMENTS_DEFAULT_CURRENCY', 'USD'),

    'webhook' => [
        // Public URL prefix the gateway should POST callbacks to. Per-driver
        // path segments append automatically — e.g. /payments/webhooks/paynow.
        'base_url' => env('APP_URL').'/payments/webhooks',

        // Rolling window for replay-attack rejection. Drivers that pass a
        // timestamp in the callback compare it against now() and drop
        // anything older than this many seconds.
        'replay_window_seconds' => 600,
    ],

    'idempotency' => [
        // PaymentManager::charge($gateway, $request, $idempotencyKey)
        // caches the original ChargeResult under this key for the TTL.
        // Same key inside the window returns the cached result instead
        // of re-charging. Header callers (HTTP) should send Idempotency-Key.
        'header' => env('PAYMENTS_IDEMPOTENCY_HEADER', 'Idempotency-Key'),
        'ttl_seconds' => (int) env('PAYMENTS_IDEMPOTENCY_TTL', 86400),
        // Empty string uses the default cache store.
        'cache_store' => env('PAYMENTS_IDEMPOTENCY_CACHE_STORE', ''),
    ],

    'sandbox' => [
        // Hard refuse to boot in production unless explicitly allowed
        // (§9). Two-layer guard: this flag + the driver constructor.
        'allow_production' => filter_var(env('SANDBOX_PAYMENTS_ALLOW_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN),

        // Caller-supplied `idempotency_key` retains the original
        // response for this many seconds (§15 #8).
        'idempotency_ttl_seconds' => (int) env('SANDBOX_IDEMPOTENCY_TTL', 86400),

        // How long an AUTHORIZED hold is valid before auto-void (§15 #7).
        'auth_expiry_seconds' => (int) env('SANDBOX_AUTH_EXPIRY', 604800),

        // Default merchant slug for tests that don't pass one explicitly.
        'default_merchant' => env('SANDBOX_DEFAULT_MERCHANT', 'sandbox-default'),

        // HTTP-served mode (§7, phase 6). When `kernel=http` and
        // `base_url` is set, the SandboxGateway driver should proxy to
        // a standalone sandbox service instead of in-process. The
        // standalone service is not shipped yet — this slot defines
        // the contract so the driver can switch when it lands.
        'kernel' => env('SANDBOX_KERNEL', 'in_process'),     // in_process|http
        'base_url' => env('SANDBOX_URL'),                    // only used when kernel=http
        'service_key' => env('SANDBOX_SERVICE_KEY'),         // bearer token for the http service
    ],

    'reconciliation' => [
        // PollPendingPaymentsCommand runs on this cadence. Anything still
        // PENDING after `stale_after_minutes` gets a status check; anything
        // PENDING after `expire_after_minutes` is marked FAILED.
        'stale_after_minutes' => 5,
        'expire_after_minutes' => 60,
        'max_attempts' => 24,
    ],

    'gateways' => [

        'ecocash' => [
            'driver' => EcoCashGateway::class,
            'enabled' => filter_var(env('ECOCASH_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'label' => 'EcoCash',

            'base_url' => env('ECOCASH_BASE_URL', 'https://payonline.econet.co.zw/ecocashGateway-preprod/payment/v1'),
            'auth' => [
                // Basic auth header used by the EcoCash gateway. Stored
                // pre-encoded so we never log the cleartext username:password.
                'username' => env('ECOCASH_USERNAME'),
                'password' => env('ECOCASH_PASSWORD'),
            ],
            'merchant' => [
                'code' => env('ECOCASH_MERCHANT_CODE'),
                'pin' => env('ECOCASH_MERCHANT_PIN'),
                'number' => env('ECOCASH_MERCHANT_NUMBER'),
                'name' => env('ECOCASH_MERCHANT_NAME', 'Merchant'),
                'super_name' => env('ECOCASH_SUPER_MERCHANT_NAME', 'Merchant'),
                'terminal_id' => env('ECOCASH_TERMINAL_ID', 'TERM000001'),
                'location' => env('ECOCASH_LOCATION', 'Harare'),
                'country_code' => env('ECOCASH_COUNTRY_CODE', 'ZW'),
            ],
            'supported_currencies' => ['USD', 'ZiG'],
            'channel' => env('ECOCASH_CHANNEL', 'WEB'),

            'http' => [
                'timeout' => 30,
                'connect_timeout' => 10,
                'retries' => 2,
            ],
        ],

        'paynow' => [
            'driver' => PaynowGateway::class,
            'enabled' => filter_var(env('PAYNOW_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'label' => 'Paynow',

            'base_url' => env('PAYNOW_BASE_URL', 'https://www.paynow.co.zw'),
            'integration_id' => env('PAYNOW_INTEGRATION_ID'),
            'integration_key' => env('PAYNOW_INTEGRATION_KEY'),

            // Express checkout (mobile-money initiated). Distinct keys from
            // the web hosted-page integration are common in production.
            'express' => [
                'integration_id' => env('PAYNOW_EXPRESS_INTEGRATION_ID', env('PAYNOW_INTEGRATION_ID')),
                'integration_key' => env('PAYNOW_EXPRESS_INTEGRATION_KEY', env('PAYNOW_INTEGRATION_KEY')),
            ],

            'supported_currencies' => ['USD', 'ZWL'],
            'supported_methods' => ['ecocash', 'onemoney', 'innbucks', 'omari', 'zimswitch'],

            'http' => [
                'timeout' => 30,
                'connect_timeout' => 10,
                'retries' => 2,
            ],
        ],

        'pesepay' => [
            'driver' => PesepayGateway::class,
            'enabled' => filter_var(env('PESEPAY_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'label' => 'Pesepay',

            'base_url' => env('PESEPAY_BASE_URL', 'https://api.pesepay.com/api/payments-engine'),
            'integration_key' => env('PESEPAY_INTEGRATION_KEY'),
            // Encryption key (32 chars) drives AES-256-CBC of the payload.
            'encryption_key' => env('PESEPAY_ENCRYPTION_KEY'),

            'supported_currencies' => ['USD', 'ZWL', 'ZAR'],

            'http' => [
                'timeout' => 30,
                'connect_timeout' => 10,
                'retries' => 2,
            ],
        ],

        'zimswitch' => [
            'driver' => ZimswitchGateway::class,
            'enabled' => filter_var(env('ZIMSWITCH_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'label' => 'Zimswitch (OPP)',

            // OPP (Open Payment Platform) hosted by Zimswitch.
            'base_url' => env('ZIMSWITCH_BASE_URL', 'https://test.oppwa.com/v1'),
            'access_token' => env('ZIMSWITCH_ACCESS_TOKEN'),
            'entity_id' => env('ZIMSWITCH_ENTITY_ID'),
            'webhook_decryption_key' => env('ZIMSWITCH_WEBHOOK_KEY'),

            // Brand list driving the COPYandPAY widget.
            'payment_brands' => ['VISA', 'MASTER', 'ZIMSWITCH'],
            'supported_currencies' => ['USD', 'ZWL'],

            'http' => [
                'timeout' => 30,
                'connect_timeout' => 10,
                'retries' => 2,
            ],
        ],

        'sandbox' => [
            'driver' => SandboxGateway::class,
            'enabled' => filter_var(env('SANDBOX_PAYMENTS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'label' => 'Sandbox (test only)',
            'supported_currencies' => ['USD', 'EUR', 'GBP', 'ZAR', 'ZWL', 'ZiG'],
            'http' => ['timeout' => 5, 'connect_timeout' => 5, 'retries' => 0],
        ],

        'stripe' => [
            'driver' => StripeGateway::class,
            'enabled' => filter_var(env('STRIPE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'label' => 'Stripe',

            // Server-side secret key. NEVER ship the publishable key
            // here — that's a FE concern handled by Stripe Elements.
            'secret_key' => env('STRIPE_SECRET_KEY'),
            // Endpoint signing secret (Dashboard → Developers →
            // Webhooks → reveal). Stripe-Signature verification uses
            // this for HMAC-SHA256 over `t.body`.
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            // Replay tolerance for webhook timestamps. Stripe-standard
            // default is 300s; tighten to 120s for high-value flows.
            'replay_tolerance_seconds' => (int) env('STRIPE_REPLAY_TOLERANCE', 300),

            'supported_currencies' => ['USD', 'EUR', 'GBP', 'ZAR', 'AUD', 'CAD', 'NGN'],

            'http' => [
                'timeout' => 30,
                'connect_timeout' => 10,
                'retries' => 2,
            ],
        ],

    ],

];
