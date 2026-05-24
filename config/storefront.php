<?php

use App\Services\Storefront\Fees\PlatformFeeRule;
use App\Services\Storefront\Fees\ProcessorFeeRule;
use App\Services\Storefront\Taxes\FlatRateTaxRule;

/*
|--------------------------------------------------------------------------
| Public storefront configuration
|--------------------------------------------------------------------------
|
| Drives the unauthenticated `/api/v1/public/*` surface: discovery,
| checkout sessions, ticket holds, fulfilment, and the waitlist. The
| design mirrors `config/scanning.php` — pluggable rule classes for
| anything that varies per-org (tax, fees, fulfilment).
|
| Defaults are chosen to be safe: a 15-minute hold, no platform fees,
| no tax. An organizer who wants either turns them on by env or by
| dropping a rule class into the relevant list.
|
*/

return [

    'checkout' => [
        // How long an unfinished cart holds inventory before it's swept.
        // Industry norm is 10 min (Ticketmaster) to 15 min (Eventbrite).
        // The expiry job runs every minute; an aggressive value here will
        // surface as more "your hold expired" errors during peak sales.
        'hold_ttl_minutes' => (int) env('STOREFRONT_HOLD_TTL_MINUTES', 15),

        // Grace period after expiry before the row is hard-released.
        // Buyers who finalised payment just under the wire occasionally
        // race the expiry sweep; this protects them.
        'expiry_grace_seconds' => (int) env('STOREFRONT_EXPIRY_GRACE_SECONDS', 30),

        // Max active sessions a single IP can have at once. Cheap
        // anti-bot for the create-session endpoint; tune up for venues
        // with shared NAT (festivals on wifi).
        'max_open_sessions_per_ip' => (int) env('STOREFRONT_MAX_SESSIONS_PER_IP', 3),

        // Hard ceiling on items added to a single cart. Prevents a bot
        // from creating a session that locks half the inventory.
        'absolute_max_tickets_per_session' => (int) env('STOREFRONT_MAX_TICKETS', 50),
    ],

    'pricing' => [
        // Rule classes invoked in order on every recompute. Each rule
        // receives the running PriceQuote and may add line items.
        // Drop a class in to extend; remove one to disable.
        'fee_rules' => [
            PlatformFeeRule::class,
            ProcessorFeeRule::class,
        ],

        'tax_rules' => [
            FlatRateTaxRule::class,
        ],

        // Default platform take when the organization hasn't set their
        // own override. Expressed as basis points (250 = 2.5%).
        'platform_fee_bps' => (int) env('STOREFRONT_PLATFORM_FEE_BPS', 250),
        'platform_fee_flat_cents' => (int) env('STOREFRONT_PLATFORM_FEE_FLAT_CENTS', 99),

        // Processor pass-through — what the payment gateway charges us
        // per transaction, exposed transparently in the receipt so the
        // buyer sees what they're really paying for.
        'processor_fee_bps' => (int) env('STOREFRONT_PROCESSOR_FEE_BPS', 290),
        'processor_fee_flat_cents' => (int) env('STOREFRONT_PROCESSOR_FEE_FLAT_CENTS', 30),

        // Who eats fees: 'buyer' (added to total), 'organizer' (deducted
        // from payout), 'split' (50/50). Buyer-pays is the storefront
        // default because organizers expect the listed ticket price to
        // be what they receive.
        'fee_payer' => env('STOREFRONT_FEE_PAYER', 'buyer'),
    ],

    'tax' => [
        // Default flat rate when the org or the event hasn't overridden.
        // VAT in Zimbabwe is 15% as of 2024; pick a sensible default for
        // the platform's primary jurisdiction.
        'default_rate_bps' => (int) env('STOREFRONT_TAX_RATE_BPS', 0),
        'tax_inclusive' => env('STOREFRONT_TAX_INCLUSIVE', false) === true
            || env('STOREFRONT_TAX_INCLUSIVE') === 'true',
        'jurisdiction_label' => env('STOREFRONT_TAX_LABEL'),

        // Per-country VAT/GST rates in basis points, keyed by ISO-3166-1
        // alpha-2. Consumed by JurisdictionalTaxRule when wired into
        // pricing.tax_rules. Sensible regional defaults — set
        // STOREFRONT_TAX_RATES_JSON in env to override the whole map.
        'jurisdiction_rates' => json_decode((string) env('STOREFRONT_TAX_RATES_JSON', '{}'), true) ?: [
            'ZW' => 1500,
            'ZA' => 1500,
            'GB' => 2000,
            'IE' => 2300,
            'US' => 0,
        ],
    ],

    'fulfilment' => [
        // Whether order confirmation is queued vs. sent inline. Inline
        // is slower for the buyer but simpler to debug locally.
        'queue_confirmations' => env('STOREFRONT_QUEUE_CONFIRMATIONS', true),

        // Queue name for fulfilment jobs (ticket issuance + email).
        // Use a dedicated queue so a slow mail provider can't block
        // scan-side jobs.
        'queue' => env('STOREFRONT_QUEUE', 'storefront'),

        // How long order-view / wallet-pass signed URLs remain valid.
        // Default = 7 days; bump for events where the gate is months
        // out and buyers expect to re-open the email closer to the date.
        'signed_link_expiry_minutes' => (int) env('STOREFRONT_SIGNED_LINK_MIN', 60 * 24 * 7),
    ],

    'captcha' => [
        // Which provider to bind. Defaults to the always-passes null
        // provider so dev / sandbox runs aren't blocked.
        'provider' => env('STOREFRONT_CAPTCHA_PROVIDER', 'null'),
        'turnstile' => [
            'site_key' => env('STOREFRONT_TURNSTILE_SITE_KEY'),
            'secret_key' => env('STOREFRONT_TURNSTILE_SECRET_KEY'),
        ],
    ],

    'wallet' => [
        'apple' => [
            'cert_path' => env('STOREFRONT_APPLE_PASS_CERT_PATH'),
            'wwdr_path' => env('STOREFRONT_APPLE_PASS_WWDR_PATH'),
            'cert_passphrase' => env('STOREFRONT_APPLE_PASS_CERT_PASSPHRASE', ''),
            'team_id' => env('STOREFRONT_APPLE_PASS_TEAM_ID'),
            'pass_type_id' => env('STOREFRONT_APPLE_PASS_TYPE_ID'),
            'icon_path' => env('STOREFRONT_APPLE_PASS_ICON_PATH'),
        ],
        'google' => [
            'service_account_path' => env('STOREFRONT_GOOGLE_WALLET_SERVICE_ACCOUNT_PATH'),
            'issuer_id' => env('STOREFRONT_GOOGLE_WALLET_ISSUER_ID'),
            'class_id' => env('STOREFRONT_GOOGLE_WALLET_CLASS_ID'),
        ],
    ],

    'waitlist' => [
        // How long a notified waitlister has to convert before the slot
        // is offered to the next person in line.
        'notification_window_minutes' => (int) env('STOREFRONT_WAITLIST_WINDOW_MIN', 30),
    ],

    'discovery' => [
        // Default page size for /api/v1/public/events.
        'default_page_size' => (int) env('STOREFRONT_PAGE_SIZE', 20),
        'max_page_size' => (int) env('STOREFRONT_MAX_PAGE_SIZE', 100),

        // ISR-style cache window for the public events list. Keep
        // short — buyers expect "sold out" to reflect quickly.
        'list_cache_seconds' => (int) env('STOREFRONT_LIST_CACHE_SECONDS', 30),
    ],

    'rate_limits' => [
        // Per-IP per-minute caps for the public surface. Names align
        // with the limiters registered in PublicStorefrontServiceProvider.
        'discovery' => env('STOREFRONT_RL_DISCOVERY', '120,1'),
        'checkout_write' => env('STOREFRONT_RL_CHECKOUT', '30,1'),
        'order_lookup' => env('STOREFRONT_RL_LOOKUP', '10,1'),
        'waitlist' => env('STOREFRONT_RL_WAITLIST', '6,1'),
    ],

    // Lookup link sent to buyers in the confirmation mail — used to
    // construct the "view your tickets" URL. Public-facing front-end
    // owns the route shape; we just hand back a URL stub.
    'public_url' => env('STOREFRONT_PUBLIC_URL', env('APP_URL')),
];
