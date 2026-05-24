<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\CheckoutSession;
use App\Models\CheckoutSessionItem;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\TicketCategory;
use App\Services\Storefront\Exceptions\SeatUnavailableException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reserved-seating equivalent of TicketReservation. When an event has
 * a SeatMap, the buyer picks specific seats rather than a quantity per
 * tier. Each (session, seat) pair gets a `seat_holds` row whose unique
 * constraint on seat_id is what prevents double-booking.
 *
 * Integration:
 *   - CheckoutController exposes /sessions/{uuid}/seats (add/remove).
 *   - On hold, we also insert/update the parent `checkout_session_items`
 *     line for the tier so the existing PriceCalculator / fulfilment
 *     paths keep working unchanged.
 *
 * On fulfilment, OrderFulfillment looks up the seat hold by session,
 * issues the OfflineTicket, sets `seat_holds.order_item_id`, and
 * flips the seat's status to SOLD.
 */
class SeatedReservation
{
    public function __construct(protected PriceResolver $prices) {}

    /**
     * @param  list<string>  $seatUuids
     */
    public function hold(CheckoutSession $session, array $seatUuids): CheckoutSession
    {
        $ttl = (int) config('storefront.checkout.hold_ttl_minutes', 15);
        $expiry = Carbon::now()->addMinutes($ttl);

        return DB::transaction(function () use ($session, $seatUuids, $expiry) {
            $seats = Seat::query()
                ->whereIn('uuid', $seatUuids)
                ->with('category.currencyPrices')
                ->lockForUpdate()
                ->get();

            if ($seats->count() !== count($seatUuids)) {
                throw new SeatUnavailableException(seatUuid: 'unknown', reason: 'not_found');
            }

            foreach ($seats as $seat) {
                $other = SeatHold::query()
                    ->where('seat_id', $seat->id)
                    ->where('checkout_session_id', '!=', $session->id)
                    ->where('expires_at', '>=', Carbon::now())
                    ->exists();

                if ($other) {
                    throw new SeatUnavailableException($seat->uuid);
                }

                if ($seat->status === Seat::STATUS_SOLD || $seat->status === Seat::STATUS_BLOCKED) {
                    throw new SeatUnavailableException($seat->uuid, $seat->status);
                }
            }

            // Drop holds the buyer no longer wants.
            SeatHold::query()
                ->where('checkout_session_id', $session->id)
                ->whereNotIn('seat_id', $seats->pluck('id'))
                ->delete();

            // Upsert per-seat holds and recompute the per-tier line items.
            $byTier = [];
            foreach ($seats as $seat) {
                SeatHold::updateOrCreate(
                    ['seat_id' => $seat->id],
                    [
                        'checkout_session_id' => $session->id,
                        'expires_at' => $expiry,
                    ],
                );
                $seat->status = Seat::STATUS_HELD;
                $seat->save();

                $tierId = (int) ($seat->ticket_category_id ?? 0);
                $byTier[$tierId] = ($byTier[$tierId] ?? 0) + 1;
            }

            // Mirror the seat holds into checkout_session_items so the
            // unchanged PriceCalculator + OrderFulfillment paths work.
            // Only touch tiers we're managing — preserve GA / unseated
            // tier rows the buyer already added via /items.
            $managedTierIds = array_keys($byTier);

            CheckoutSessionItem::query()
                ->where('checkout_session_id', $session->id)
                ->whereIn('ticket_category_id', $managedTierIds)
                ->delete();

            foreach ($byTier as $tierId => $count) {
                if ($tierId === 0) {
                    continue;
                }
                $tier = TicketCategory::query()->find($tierId);
                if (! $tier) {
                    continue;
                }
                $unit = $this->prices->resolveUnitPriceMinor($tier, $session->currency);
                CheckoutSessionItem::create([
                    'checkout_session_id' => $session->id,
                    'ticket_category_id' => $tierId,
                    'quantity' => $count,
                    'unit_price_cents' => $unit,
                    'currency' => $session->currency,
                    'line_total_cents' => $unit * $count,
                    'price_locked_at' => Carbon::now(),
                ]);
            }

            // Re-stamp session expiry to match the seat hold window.
            $session->forceFill(['expires_at' => $expiry])->save();

            return $session->refresh();
        });
    }

    /**
     * Release all holds for a session — called by
     * CheckoutSessionManager::cancel/expire. Restores each seat to
     * AVAILABLE unless the row was already SOLD (defensive).
     */
    public function release(CheckoutSession $session): void
    {
        $seatIds = SeatHold::query()
            ->where('checkout_session_id', $session->id)
            ->whereNull('order_item_id')
            ->pluck('seat_id');

        if ($seatIds->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($session, $seatIds) {
            Seat::query()
                ->whereIn('id', $seatIds)
                ->where('status', '!=', Seat::STATUS_SOLD)
                ->update(['status' => Seat::STATUS_AVAILABLE]);

            SeatHold::query()
                ->where('checkout_session_id', $session->id)
                ->whereNull('order_item_id')
                ->delete();
        });
    }
}
