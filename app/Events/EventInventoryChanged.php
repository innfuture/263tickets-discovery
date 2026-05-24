<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Event;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an event's purchasable inventory changes (sale, hold,
 * release, refund, void). Broadcast on a PUBLIC channel keyed by
 * event slug so the public storefront can show live "only 3 tickets
 * left" without auth.
 *
 * Skipped when the broadcaster is null.
 */
class EventInventoryChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Event $event,
        /** @var array<int, array{ticket_category_id:int, available:int|null}> */
        public array $availability,
    ) {}

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('storefront.events.'.$this->event->slug)];
    }

    public function broadcastAs(): string
    {
        return 'inventory.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'event_slug' => $this->event->slug,
            'tickets_sold' => (int) $this->event->tickets_sold_count,
            'is_sold_out' => $this->event->isSoldOut(),
            'tiers' => $this->availability,
        ];
    }

    public function broadcastWhen(): bool
    {
        return (string) config('broadcasting.default') !== 'null';
    }
}
