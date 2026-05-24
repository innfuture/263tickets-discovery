<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\CheckoutSession;
use App\Models\CheckoutSessionItem;
use App\Models\TicketCategory;
use App\Services\Storefront\Data\CartLineInput;
use App\Services\Storefront\Exceptions\InventoryUnavailableException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Authoritative source for inventory holds. Every mutation to a
 * session's items goes through here so the availability check + write
 * happen in the same transaction.
 *
 * Inventory math, per ticket category:
 *
 *   available = capacity
 *               − issued_tickets_count             (paid + fulfilled)
 *               − sum(open_session_holds.quantity) (other carts)
 *
 * The open-session count is computed under a row-lock on
 * `checkout_session_items` for the tier so two simultaneous
 * adds-to-cart can't both pass the check.
 *
 * Capacity sources, in order:
 *   1. `ticket_categories.online_quantity` if > 0
 *   2. `events.capacity` (whole-event cap) as a backstop
 *
 * If neither is set, the tier is treated as unlimited.
 */
class TicketReservation
{
    public function __construct(protected PriceResolver $prices) {}

    /**
     * Replace the session's lines with the given inputs. We diff
     * rather than delete-then-recreate so the unique constraint on
     * (session_id, ticket_category_id) never trips.
     *
     * @param  list<CartLineInput>  $lines
     */
    public function syncLines(CheckoutSession $session, array $lines): CheckoutSession
    {
        return DB::transaction(function () use ($session, $lines) {
            $byTier = [];
            foreach ($lines as $line) {
                $byTier[$line->ticketCategoryId] = ($byTier[$line->ticketCategoryId] ?? 0) + $line->quantity;
            }

            $absoluteMax = (int) config('storefront.checkout.absolute_max_tickets_per_session', 50);
            $totalQty = array_sum($byTier);
            if ($totalQty > $absoluteMax) {
                throw new InventoryUnavailableException(
                    ticketCategoryId: 0,
                    requested: $totalQty,
                    available: $absoluteMax,
                );
            }

            // Lock + revalidate against capacity. Pulling all impacted
            // tiers in one go means we hold locks for the minimum time.
            $categories = TicketCategory::query()
                ->whereIn('id', array_keys($byTier))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($byTier as $categoryId => $qty) {
                $category = $categories->get($categoryId);
                if (! $category) {
                    throw new InventoryUnavailableException($categoryId, $qty, 0);
                }

                $minPer = max(1, (int) ($category->min_per_order ?? 1));
                $maxPer = (int) ($category->max_per_order ?? 0);
                if ($qty > 0 && $qty < $minPer) {
                    throw new InventoryUnavailableException($categoryId, $qty, $minPer);
                }
                if ($maxPer > 0 && $qty > $maxPer) {
                    throw new InventoryUnavailableException($categoryId, $qty, $maxPer);
                }

                $available = $this->availabilityForCategory($category, $session->id);
                if ($available !== null && $qty > $available) {
                    throw new InventoryUnavailableException($categoryId, $qty, $available);
                }
            }

            // ── persist the diff ──────────────────────────────────────
            $existing = $session->items()->get()->keyBy('ticket_category_id');

            foreach ($byTier as $categoryId => $qty) {
                $category = $categories->get($categoryId);
                if (! $category) {
                    continue;
                }

                if ($qty === 0) {
                    $existing->get($categoryId)?->delete();

                    continue;
                }

                $unit = $this->prices->resolveUnitPriceMinor($category, $session->currency);
                $row = $existing->get($categoryId) ?? new CheckoutSessionItem([
                    'checkout_session_id' => $session->id,
                    'ticket_category_id' => $categoryId,
                ]);
                $row->fill([
                    'quantity' => $qty,
                    'unit_price_cents' => $unit,
                    'currency' => $session->currency,
                    'line_total_cents' => $unit * $qty,
                    'price_locked_at' => Carbon::now(),
                ])->save();
            }

            // Drop any rows for tiers no longer in the request.
            $touchedIds = array_keys($byTier);
            $session->items()
                ->whereNotIn('ticket_category_id', $touchedIds)
                ->get()
                ->each->delete();

            $session->refresh()->load('items');

            return $session;
        });
    }

    /**
     * Returns null for an unlimited tier (no cap configured), otherwise
     * the number of seats a buyer could still add to the given session.
     * Holds inside `$excludingSessionId` are excluded so a buyer can
     * change quantity inside their own cart freely.
     */
    public function availabilityForCategory(TicketCategory $category, ?int $excludingSessionId = null): ?int
    {
        $tierCap = (int) ($category->online_quantity ?? 0);
        $eventCap = (int) ($category->event?->capacity ?? 0);

        // Neither cap = unlimited (logical, e.g. donation tickets).
        if ($tierCap <= 0 && $eventCap <= 0) {
            return null;
        }

        $issued = (int) $category->offlineTickets()->whereNull('voided_at')->count();

        $heldByOthers = (int) CheckoutSessionItem::query()
            ->where('ticket_category_id', $category->id)
            ->when($excludingSessionId, fn ($q, $id) => $q->where('checkout_session_id', '!=', $id))
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('checkout_sessions')
                    ->whereColumn('checkout_sessions.id', 'checkout_session_items.checkout_session_id')
                    ->whereIn('checkout_sessions.status', ['open', 'paying'])
                    ->where('checkout_sessions.expires_at', '>=', Carbon::now());
            })
            ->sum('quantity');

        $cap = $tierCap > 0 ? $tierCap : $eventCap;

        return max(0, $cap - $issued - $heldByOthers);
    }

    /**
     * Release every hold on a session (cancel / expire). Doesn't touch
     * session status — caller owns that transition so the same release
     * path can be triggered by multiple terminal states.
     */
    public function release(CheckoutSession $session): void
    {
        $session->items()->delete();
    }
}
