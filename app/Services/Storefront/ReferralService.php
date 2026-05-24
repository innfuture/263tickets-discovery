<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\GiftCard;
use App\Models\Order;
use App\Models\Organization;
use App\Models\ReferralCode;
use App\Models\ReferralCredit;
use Illuminate\Support\Facades\DB;

/**
 * Mints + credits referral codes. Reuses GiftCardManager to deliver
 * the reward — no parallel "store credit" system. Idempotent per
 * (referral_code, order) thanks to the unique constraint on
 * `referral_credits`.
 */
class ReferralService
{
    public function __construct(protected GiftCardManager $giftCards) {}

    public function mint(
        Organization $org,
        string $ownerEmail,
        ?string $ownerName,
        int $rewardCents,
        string $rewardCurrency,
        ?int $maxRewards = null,
    ): ReferralCode {
        return ReferralCode::create([
            'organisation_id' => $org->uuid,
            'owner_email' => strtolower($ownerEmail),
            'owner_name' => $ownerName,
            'reward_cents' => $rewardCents,
            'reward_currency' => strtoupper($rewardCurrency),
            'max_rewards' => $maxRewards,
            'is_active' => true,
        ]);
    }

    /**
     * Credit the referrer for an order that used their code. Called
     * by an OrderPaid listener; safe to invoke multiple times for the
     * same (order, code) — the unique row guards re-credit.
     */
    public function credit(ReferralCode $code, Order $order): ?ReferralCredit
    {
        if (! $code->isUsable()) {
            return null;
        }
        if ($code->reward_currency !== $order->currency) {
            return null;
        }

        return DB::transaction(function () use ($code, $order) {
            $existing = ReferralCredit::query()
                ->where('referral_code_id', $code->id)
                ->where('order_id', $order->id)
                ->first();
            if ($existing) {
                return $existing;
            }

            $card = $this->giftCards->issue(
                org: $code->organization,
                balanceCents: (int) $code->reward_cents,
                currency: (string) $code->reward_currency,
                source: GiftCard::SOURCE_PROMO,
                recipientEmail: $code->owner_email,
                recipientName: $code->owner_name,
                message: 'Thanks for the referral! Order '.$order->reference,
            );

            $credit = ReferralCredit::create([
                'referral_code_id' => $code->id,
                'order_id' => $order->id,
                'gift_card_id' => $card->id,
                'amount_cents' => (int) $code->reward_cents,
                'currency' => (string) $code->reward_currency,
            ]);

            $code->increment('rewards_count');

            return $credit;
        });
    }
}
