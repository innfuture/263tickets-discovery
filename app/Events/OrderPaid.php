<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by OrderFulfillment::fulfill() once the order is committed
 * and all OfflineTickets are issued. Listeners may safely assume:
 *
 *   - $order->items is populated
 *   - the underlying CheckoutSession is in `completed` status
 *   - inventory holds are released (the items table now carries the seats)
 *
 * Broadcast channel mirrors TicketScanned — private per-org so the
 * organizer dashboard live revenue counter updates. Skipped when the
 * broadcaster is null (dev / sandbox).
 */
class OrderPaid implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order) {}

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('organization.'.$this->order->organisation_id.'.orders'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.paid';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'order_uuid' => $this->order->uuid,
            'reference' => $this->order->reference,
            'total_cents' => (int) $this->order->total_cents,
            'currency' => (string) $this->order->currency,
            'event_id' => $this->order->event_id,
            'placed_at' => optional($this->order->placed_at)->toIso8601String(),
        ];
    }

    public function broadcastWhen(): bool
    {
        return (string) config('broadcasting.default') !== 'null';
    }
}
