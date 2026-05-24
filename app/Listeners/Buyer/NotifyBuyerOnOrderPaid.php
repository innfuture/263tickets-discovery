<?php

declare(strict_types=1);

namespace App\Listeners\Buyer;

use App\Events\OrderPaid;
use App\Models\Buyer;
use App\Models\BuyerNotification;
use App\Services\Buyers\BuyerNotificationService;

/**
 * On every OrderPaid that has a buyer_id, drop a notification into
 * the buyer's bell. Pre-existing guest orders without a linked
 * buyer get linked retroactively on first login (see
 * BuyerAuthService::verifyToken), so this listener won't notify
 * them — they'll see the order in `upcoming` instead.
 */
class NotifyBuyerOnOrderPaid
{
    public function __construct(protected BuyerNotificationService $notifications) {}

    public function handle(OrderPaid $event): void
    {
        $order = $event->order;
        if (! $order->buyer_id) {
            return;
        }

        $buyer = Buyer::query()->find($order->buyer_id);
        if (! $buyer) {
            return;
        }

        $this->notifications->record(
            buyer: $buyer,
            type: BuyerNotification::TYPE_ORDER_CONFIRMED,
            title: 'Order confirmed: '.$order->reference,
            body: $order->event?->name
                ? "Your tickets for {$order->event->name} are ready."
                : 'Your order is confirmed.',
            data: [
                'order_reference' => $order->reference,
                'event_slug' => $order->event?->slug,
                'total_cents' => (int) $order->total_cents,
                'currency' => $order->currency,
            ],
            actionUrl: '/buyer/orders/'.$order->reference,
        );
    }
}
