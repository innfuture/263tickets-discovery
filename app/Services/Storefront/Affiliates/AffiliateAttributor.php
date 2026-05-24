<?php

declare(strict_types=1);

namespace App\Services\Storefront\Affiliates;

use App\Models\AffiliateAttribution;
use App\Models\AffiliateCode;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Resolves an affiliate code (case-insensitive, current-window-aware)
 * and attaches it to an Order — capturing the commission at sale
 * time so future rate changes don't retroactively alter payouts.
 *
 * One attribution per Order (enforced by unique index). If a buyer
 * passes a different code on retry, the first one wins.
 *
 * Distinct from the older ReferralCode/ReferralCredit system which
 * is a buyer gift-credit mechanism; this one pays external partners
 * a cut of each sale they refer.
 */
class AffiliateAttributor
{
    public function resolveCode(string $organizationId, string $code, ?int $eventId = null): ?AffiliateCode
    {
        return AffiliateCode::query()
            ->where('organization_id', $organizationId)
            ->where('code', strtoupper($code))
            ->where(function ($q) use ($eventId): void {
                $q->whereNull('event_id');
                if ($eventId !== null) {
                    $q->orWhere('event_id', $eventId);
                }
            })
            ->first();
    }

    /**
     * Attach a code to an order. Returns the attribution row, or
     * null if the code is not redeemable. Idempotent — calling twice
     * with the same code/order is a no-op.
     */
    public function attribute(AffiliateCode $code, Order $order): ?AffiliateAttribution
    {
        if (! $code->isRedeemable()) {
            return null;
        }

        return DB::transaction(function () use ($code, $order): ?AffiliateAttribution {
            // Re-load with a row lock so concurrent attribution attempts
            // don't race on uses_count.
            $code = AffiliateCode::query()->lockForUpdate()->find($code->id);
            if (! $code || ! $code->isRedeemable()) {
                return null;
            }

            $existing = AffiliateAttribution::query()
                ->where('order_id', $order->id)
                ->first();
            if ($existing) {
                return $existing;
            }

            $commission = $this->computeCommission($code, $order);

            $attribution = AffiliateAttribution::create([
                'affiliate_code_id' => $code->id,
                'order_id' => $order->id,
                'commission_cents' => $commission,
                'currency' => $code->currency ?? $order->currency,
                'settlement_status' => AffiliateAttribution::STATUS_PENDING,
                'attributed_at' => CarbonImmutable::now(),
            ]);

            $code->increment('uses_count');

            return $attribution;
        });
    }

    /**
     * Commission = flat_per_ticket × ticket_count + (bps × order_subtotal / 10_000)
     * Either component may be null/zero. Capped at order subtotal to
     * prevent negative payouts on weird configs.
     */
    protected function computeCommission(AffiliateCode $code, Order $order): int
    {
        $order->loadMissing('items');
        $ticketCount = (int) $order->items->sum('quantity');
        $subtotal = (int) $order->subtotal_cents;

        $perTicket = ($code->commission_flat_cents_per_ticket ?? 0) * $ticketCount;
        $perBps = (int) floor(($subtotal * ($code->commission_bps ?? 0)) / 10_000);

        $total = $perTicket + $perBps;

        return max(0, min($total, $subtotal));
    }

    /**
     * Mark an attribution as accrued — typically called after the
     * organizer settles the order (cleared funds). Idempotent.
     */
    public function accrue(AffiliateAttribution $attribution): AffiliateAttribution
    {
        if ($attribution->settlement_status === AffiliateAttribution::STATUS_PENDING) {
            $attribution->forceFill(['settlement_status' => AffiliateAttribution::STATUS_ACCRUED])->save();
        }

        return $attribution->fresh();
    }

    /**
     * Reverse an attribution — refund / chargeback path.
     */
    public function reverse(AffiliateAttribution $attribution, string $reason = ''): AffiliateAttribution
    {
        if ($attribution->settlement_status === AffiliateAttribution::STATUS_PAID) {
            throw new RuntimeException("Cannot reverse attribution {$attribution->id}: already paid out.");
        }

        $attribution->forceFill([
            'settlement_status' => AffiliateAttribution::STATUS_REVERSED,
            'settled_at' => CarbonImmutable::now(),
        ])->save();

        return $attribution->fresh();
    }
}
