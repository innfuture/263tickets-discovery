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
use Illuminate\Support\Facades\Mail;

/**
 * Sends the buyer their tickets + receipt. This is the side-effect
 * counterpart to OrderPaid. Kept as its own job so a slow SMTP /
 * SendGrid hiccup doesn't block the gateway webhook listener.
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

        Mail::to($order->buyer_email)->send(new OrderConfirmationMail($order));
    }
}
