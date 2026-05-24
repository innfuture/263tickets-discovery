<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Storefront strings
|--------------------------------------------------------------------------
|
| User-facing labels surfaced by the public storefront engine: receipt
| line labels (subtotal / fees / tax), error messages, and email copy.
|
| To localize: copy this file to lang/<locale>/storefront.php and
| translate. The Checkout flow picks the locale from
| `App::getLocale()` which is set per-request by SetLocaleMiddleware
| using the `Accept-Language` header (or the session's saved choice
| for the authenticated portal).
|
*/

return [
    'lines' => [
        'tickets' => 'Tickets',
        'service_fee' => 'Service fee',
        'processing_fee' => 'Payment processing',
        'tax' => 'Tax',
        'tax_inclusive' => ':label (incl.)',
        'discount' => 'Discount',
        'promo' => 'Promo: :code',
    ],

    'errors' => [
        'cart_empty' => 'Your cart is empty.',
        'buyer_details_required' => 'Please add buyer details before paying.',
        'session_locked' => 'This checkout session can no longer be modified.',
        'session_expired' => 'This checkout session has expired. Please start again.',
        'rate_limited' => 'Too many active checkout sessions from your network. Try again in a few minutes.',
        'event_not_purchasable' => 'This event is not currently selling tickets.',
        'inventory_unavailable' => 'Only :available ticket(s) remain in this tier.',
        'currency_mismatch' => 'This ticket tier is not available in :currency.',
        'gateway_unavailable' => 'That payment method is currently unavailable.',
    ],

    'promo' => [
        'PROMO_NOT_FOUND' => "That code isn't recognised.",
        'PROMO_INACTIVE' => 'That code is no longer active.',
        'PROMO_EXPIRED' => 'That code has expired.',
        'PROMO_EXHAUSTED' => 'That code has been fully redeemed.',
        'PROMO_NOT_APPLICABLE' => "That code doesn't apply to anything in your cart.",
    ],

    'refund' => [
        'not_refundable' => 'This order is not eligible for a refund — contact the organizer directly.',
        'submitted' => 'Your refund request has been submitted. The organizer will be in touch.',
    ],

    'waitlist' => [
        'available_subject' => 'Tickets are available — :event',
        'available_body' => 'Capacity has freed up. Complete checkout within :window minutes to secure your tickets.',
    ],

    'email' => [
        'confirmation_subject' => 'Your tickets — :brand · :event',
        'confirmation_intro' => 'Thank you, :name — your order is confirmed.',
        'view_tickets' => 'View tickets',
        'link_expiry_note' => 'This link is valid for a limited time — re-request from the storefront if it expires.',
    ],
];
