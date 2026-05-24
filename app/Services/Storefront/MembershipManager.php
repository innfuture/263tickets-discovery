<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\BuyerMembership;
use App\Models\BuyerMembershipPeriod;
use App\Models\Order;
use App\Models\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Issues + renews `BuyerMembership` rows. Renewal is buyer-driven
 * (we email a renew link; checkout creates a new Order; this service
 * extends the membership). Auto-renew is supported on paper but
 * needs a stored payment method — feature-flagged off until we have
 * that on the public surface.
 */
class MembershipManager
{
    public function startNew(
        Organization $org,
        string $planName,
        ?string $planDescription,
        int $priceCents,
        string $currency,
        int $periodMonths,
        string $memberEmail,
        string $memberName,
        ?Order $order = null,
    ): BuyerMembership {
        $now = Carbon::now();
        $endsAt = $now->copy()->addMonthsNoOverflow($periodMonths);

        return DB::transaction(function () use ($org, $planName, $planDescription, $priceCents, $currency, $periodMonths, $memberEmail, $memberName, $order, $now, $endsAt) {
            $m = BuyerMembership::create([
                'organisation_id' => $org->uuid,
                'plan_name' => $planName,
                'plan_description' => $planDescription,
                'price_cents' => $priceCents,
                'currency' => strtoupper($currency),
                'period_months' => $periodMonths,
                'member_email' => strtolower($memberEmail),
                'member_name' => $memberName,
                'status' => BuyerMembership::STATUS_ACTIVE,
                'starts_at' => $now,
                'ends_at' => $endsAt,
            ]);

            BuyerMembershipPeriod::create([
                'membership_id' => $m->id,
                'order_id' => $order?->id,
                'starts_at' => $now,
                'ends_at' => $endsAt,
                'paid_cents' => $priceCents,
                'currency' => strtoupper($currency),
            ]);

            return $m;
        });
    }

    public function renew(BuyerMembership $m, Order $order, ?int $priceCents = null): BuyerMembership
    {
        return DB::transaction(function () use ($m, $order, $priceCents) {
            // Renewal extends from whichever is later — current end
            // date or now — so an early renewal doesn't lose time.
            $start = $m->ends_at && $m->ends_at->isFuture() ? $m->ends_at : Carbon::now();
            $end = $start->copy()->addMonthsNoOverflow($m->period_months);

            $m->forceFill([
                'status' => BuyerMembership::STATUS_ACTIVE,
                'ends_at' => $end,
                'renewed_at' => Carbon::now(),
            ])->save();

            BuyerMembershipPeriod::create([
                'membership_id' => $m->id,
                'order_id' => $order->id,
                'starts_at' => $start,
                'ends_at' => $end,
                'paid_cents' => $priceCents ?? $m->price_cents,
                'currency' => $m->currency,
            ]);

            return $m;
        });
    }

    public function cancel(BuyerMembership $m): BuyerMembership
    {
        $m->forceFill([
            'status' => BuyerMembership::STATUS_CANCELLED,
            'cancelled_at' => Carbon::now(),
        ])->save();

        return $m;
    }

    /**
     * Marks memberships whose `ends_at` is in the past as expired.
     * Called by a daily cron. Doesn't send renewal emails — that's
     * a separate job triggered N days before expiry.
     */
    public function expireDue(?Carbon $now = null): int
    {
        $now ??= Carbon::now();

        return (int) BuyerMembership::query()
            ->where('status', BuyerMembership::STATUS_ACTIVE)
            ->where('ends_at', '<', $now)
            ->update([
                'status' => BuyerMembership::STATUS_EXPIRED,
            ]);
    }
}
