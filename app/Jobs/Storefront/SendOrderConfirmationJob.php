<?php

declare(strict_types=1);

namespace App\Jobs\Storefront;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the buyer their tickets + receipt. This is the side-effect
 * counterpart to OrderPaid. Kept as its own job so a slow SMTP /
 * SendGrid hiccup doesn't block the gateway webhook listener.
 *
 * The actual Mailable lives in app/Mail (TODO: implement
 * `OrderConfirmationMail` — see STOREFRONT.md gaps). Until then we
 * log a structured "would-have-sent" line so the rest of the chain
 * is testable end-to-end.
 */
class SendOrderConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        $order = Order::query()->with('items.category', 'event')->find($this->orderId);
        if (! $order) {
            return;
        }

        if (! class_exists(OrderConfirmationMail::class)) {
            Log::info('storefront.order_confirmation.skipped', [
                'order_id' => $order->id,
                'reason' => 'OrderConfirmationMail class not present',
                'buyer_email' => $order->buyer_email,
            ]);

            return;
        }

        Mail::to($order->buyer_email)->send(new OrderConfirmationMail($order));
    }
}
