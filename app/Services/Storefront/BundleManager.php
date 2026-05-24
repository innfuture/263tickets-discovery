<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\EventBundle;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Issues per-event tickets for a paid bundle purchase. Called by the
 * fulfilment pipeline whenever an Order's metadata carries a
 * `bundle_id` — one bundle row → N OfflineTickets (one per included
 * event, multiplied by `bundle_events.quantity`).
 *
 * Inventory: bundle has its own `total_capacity` checked at add-to-cart;
 * per-event tier inventory is still respected here so a bundle can't
 * oversubscribe a sold-out night.
 */
class BundleManager
{
    public function __construct(protected OrderFulfillment $fulfilment) {}

    public function issueForOrder(Order $order, EventBundle $bundle): void
    {
        $bundle->loadMissing('includedEvents.event', 'includedEvents.category');

        DB::transaction(function () use ($order, $bundle) {
            foreach ($bundle->includedEvents as $included) {
                $event = $included->event;
                if (! $event) {
                    continue;
                }

                $tier = $included->category
                    ?? $event->ticketCategories()->where('is_visible', true)->first();
                if (! $tier) {
                    continue;
                }

                for ($n = 0; $n < (int) $included->quantity; $n++) {
                    $ticket = $this->fulfilment->issueOfflineTicketDirectly($event, $tier);

                    $order->items()->create([
                        'ticket_category_id' => $tier->id,
                        'offline_ticket_id' => $ticket->id,
                        'attendee_name' => $order->buyer_name,
                        'attendee_email' => $order->buyer_email,
                        'ticket_type' => $bundle->name.' · '.$event->name,
                        'unit_price_cents' => 0,
                        'currency' => $order->currency,
                        'qr_payload' => $ticket->qr_payload,
                    ]);
                }

                $event->incrementTicketsSold((int) $included->quantity);
            }

            $bundle->increment('sold_count');
        });
    }

    public function isAvailable(EventBundle $bundle): bool
    {
        if (! $bundle->is_visible) {
            return false;
        }
        if ($bundle->sales_start_at && $bundle->sales_start_at->isFuture()) {
            return false;
        }
        if ($bundle->sales_end_at && $bundle->sales_end_at->isPast()) {
            return false;
        }
        $remaining = $bundle->remainingCapacity();
        if ($remaining !== null && $remaining <= 0) {
            return false;
        }

        return true;
    }
}
