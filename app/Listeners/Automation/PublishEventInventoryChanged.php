<?php

declare(strict_types=1);

namespace App\Listeners\Automation;

use App\Events\EventInventoryChanged;
use App\Models\Organization;
use App\Services\Automation\AutomationDispatcher;

/**
 * EventInventoryChanged → either `event.sold_out` (when the event
 * is now sold out) or `event.inventory_changed`. We emit both names
 * for the sold-out case so org-side workflows can subscribe to the
 * narrower one if they want fewer triggers.
 */
class PublishEventInventoryChanged
{
    public function __construct(protected AutomationDispatcher $dispatcher) {}

    public function handle(EventInventoryChanged $event): void
    {
        $org = Organization::query()->where('uuid', $event->event->organisation_id)->first();
        if (! $org) {
            return;
        }

        $payload = [
            'event' => [
                'slug' => $event->event->slug,
                'name' => $event->event->name,
                'tickets_sold' => (int) $event->event->tickets_sold_count,
                'capacity' => $event->event->capacity,
                'is_sold_out' => $event->event->isSoldOut(),
            ],
            'tiers' => $event->availability,
        ];

        $this->dispatcher->dispatch('event.inventory_changed', $org, $payload);

        if ($event->event->isSoldOut()) {
            $this->dispatcher->dispatch('event.sold_out', $org, $payload);
        }
    }
}
