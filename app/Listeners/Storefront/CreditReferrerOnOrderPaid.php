<?php

declare(strict_types=1);

namespace App\Listeners\Storefront;

use App\Events\OrderPaid;
use App\Models\ReferralCode;
use App\Services\Storefront\ReferralService;

/**
 * Reads the `referral_code` stashed in the session's metadata (set
 * by CheckoutController when the buyer hit `?ref=CODE`) and credits
 * the referrer via gift_cards.
 *
 * Skips silently when there's no referral metadata or the code is
 * invalid — referral is purely additive, never blocks fulfilment.
 */
class CreditReferrerOnOrderPaid
{
    public function __construct(protected ReferralService $referrals) {}

    public function handle(OrderPaid $event): void
    {
        $order = $event->order;
        $codeString = $order->metadata['referral_code'] ?? null;
        if (! is_string($codeString) || $codeString === '') {
            return;
        }

        $code = ReferralCode::query()->where('code', strtoupper(trim($codeString)))->first();
        if (! $code) {
            return;
        }

        $this->referrals->credit($code, $order);
    }
}
