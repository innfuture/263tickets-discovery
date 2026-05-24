<?php

/*
|--------------------------------------------------------------------------
| Automation (n8n / Zapier / Make / Pipedream) configuration
|--------------------------------------------------------------------------
|
| Drives `/api/v1/automations/*` (inbound bearer-token API) and the
| outbound webhook fan-out from domain events to subscribed
| `organization_webhooks` rows.
|
*/

return [
    // Dedicated queue so a slow downstream automation can't block
    // payment / scanner-side jobs. Add this queue to your queue:work
    // command in deployment.
    'queue' => env('AUTOMATION_QUEUE', 'automations'),

    // Per-attempt HTTP timeout when delivering a webhook.
    'timeout_seconds' => (int) env('AUTOMATION_HTTP_TIMEOUT', 10),

    // Per-IP per-minute rate limit on the inbound automation API.
    // n8n flows tend to be bursty (single trigger → many API calls);
    // tune up if you see legitimate flows being throttled.
    'rate_limit' => env('AUTOMATION_RATE_LIMIT', '120,1'),

    // Default scope set offered in the dashboard when minting a new
    // automation token. Mirror the constants in AutomationToken.
    'default_scopes' => ['read'],

    // Transactional outbox. When true (default), AutomationDispatcher
    // writes to webhook_outbox inside the caller's DB transaction;
    // DispatchPendingOutboxWebhooksJob drains the table out-of-band.
    // Set to false to revert to in-line fire-and-forget delivery.
    'use_outbox' => env('AUTOMATION_USE_OUTBOX', true),
    'outbox_max_attempts' => (int) env('AUTOMATION_OUTBOX_MAX_ATTEMPTS', 6),

    // Per-token bucket (per-minute) on the inbound automation API —
    // n8n bursts from a single host get a fair share rather than
    // being blanket-throttled with everyone else on that IP.
    'token_rate_limit_per_minute' => (int) env('AUTOMATION_TOKEN_RL', 300),

    // The list of event types we *can* publish — surfaced in the
    // dashboard so the user knows what to subscribe to.
    'event_types' => [
        'order.paid',
        'order.refund_requested',
        'order.refunded',
        'ticket.scanned',
        'event.published',
        'event.sold_out',
        'event.inventory_changed',
        'checkout.abandoned',
        'waitlist.joined',
        'waitlist.notified',
        'quote.submitted',
        'attendee.checked_in',
    ],
];
