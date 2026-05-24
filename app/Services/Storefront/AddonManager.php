<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\CheckoutSession;
use App\Models\CheckoutSessionAddon;
use App\Models\EventAddon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Manages event add-on lines (parking, merch, donations) on a
 * CheckoutSession. Independent of TicketReservation — addons don't
 * use the inventory hold model; instead they track `sold_count` /
 * `stock` directly and decrement at fulfilment time.
 *
 * The total of addon line_totals is mixed into PriceCalculator's
 * subtotal via the AddonPriceContribution rule below.
 */
class AddonManager
{
    /**
     * @param  list<array{event_addon_uuid: string, quantity: int}>  $rows
     */
    public function sync(CheckoutSession $session, array $rows): CheckoutSession
    {
        return DB::transaction(function () use ($session, $rows) {
            $hasTicket = $session->items()->exists();

            $byAddon = [];
            foreach ($rows as $row) {
                $byAddon[$row['event_addon_uuid']] = ($byAddon[$row['event_addon_uuid']] ?? 0) + (int) $row['quantity'];
            }

            // Pull every requested addon row + lock for stock check.
            $addons = EventAddon::query()
                ->whereIn('uuid', array_keys($byAddon))
                ->where('event_id', $session->event_id)
                ->where('is_visible', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('uuid');

            foreach ($byAddon as $uuid => $qty) {
                $addon = $addons->get($uuid);
                if (! $addon) {
                    throw new RuntimeException("Addon not available: {$uuid}");
                }
                if ($addon->requires_ticket && ! $hasTicket) {
                    throw new RuntimeException("Addon requires at least one ticket in the cart: {$addon->name}");
                }
                if ($qty > 0 && $qty < (int) $addon->min_per_order) {
                    throw new RuntimeException("Minimum {$addon->min_per_order} per order for {$addon->name}.");
                }
                if ($addon->max_per_order > 0 && $qty > (int) $addon->max_per_order) {
                    throw new RuntimeException("Maximum {$addon->max_per_order} per order for {$addon->name}.");
                }
                $remaining = $addon->remainingStock();
                if ($remaining !== null && $qty > $remaining) {
                    throw new RuntimeException("Only {$remaining} of {$addon->name} remaining.");
                }
            }

            $existing = $session->addons()->get()->keyBy('event_addon_id');

            foreach ($byAddon as $uuid => $qty) {
                $addon = $addons->get($uuid);
                if (! $addon) {
                    continue;
                }
                if ($qty === 0) {
                    $existing->get($addon->id)?->delete();

                    continue;
                }
                $row = $existing->get($addon->id) ?? new CheckoutSessionAddon([
                    'checkout_session_id' => $session->id,
                    'event_addon_id' => $addon->id,
                ]);
                $row->fill([
                    'quantity' => $qty,
                    'unit_price_cents' => (int) $addon->price_cents,
                    'currency' => $session->currency,
                    'line_total_cents' => (int) $addon->price_cents * $qty,
                ])->save();
            }

            // Drop any addon rows not in the new request.
            $touched = $addons->pluck('id')->all();
            $session->addons()->whereNotIn('event_addon_id', $touched)->get()->each->delete();

            return $session->refresh();
        });
    }

    /**
     * Decrement `sold_count` on the underlying EventAddon rows. Called
     * by OrderFulfillment after a successful settlement.
     */
    public function commitForOrder(CheckoutSession $session): void
    {
        $session->loadMissing('addons.addon');
        foreach ($session->addons as $row) {
            $addon = $row->addon;
            if (! $addon) {
                continue;
            }
            $addon->increment('sold_count', (int) $row->quantity);
        }
    }
}
