<?php

declare(strict_types=1);

namespace App\Listeners\Storefront;

use App\Events\EventInventoryChanged;
use App\Events\OrderPaid;
use App\Models\Event;
use App\Services\Storefront\TicketReservation;

/**
 * Fan-out: when an order completes, derive a per-tier availability
 * snapshot and broadcast it on the public event channel so the
 * storefront detail page can update "only N left" in real time.
 */
class BroadcastEventInventory
{
    public function __construct(protected TicketReservation $reservation) {}

    public function handle(OrderPaid $event): void
    {
        $domainEvent = Event::query()
            ->with('ticketCategories')
            ->find($event->order->event_id);

        if (! $domainEvent) {
            return;
        }

        $tiers = [];
        foreach ($domainEvent->ticketCategories as $tier) {
            $tiers[] = [
                'ticket_category_id' => (int) $tier->id,
                'available' => $this->reservation->availabilityForCategory($tier),
            ];
        }

        EventInventoryChanged::dispatch($domainEvent, $tiers);
    }
}
