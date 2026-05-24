<?php

declare(strict_types=1);

namespace App\Listeners\Storefront;

use App\Events\OrderPaid;
use App\Jobs\Storefront\SendOrderConfirmationJob;

/**
 * Wires OrderPaid → SendOrderConfirmationJob (queued). One layer of
 * indirection so the job can be skipped via
 * `config('storefront.fulfilment.queue_confirmations', true)` for
 * dev / sandbox runs.
 */
class QueueOrderConfirmation
{
    public function handle(OrderPaid $event): void
    {
        if (! (bool) config('storefront.fulfilment.queue_confirmations', true)) {
            return;
        }

        SendOrderConfirmationJob::dispatch($event->order->id)
            ->onQueue((string) config('storefront.fulfilment.queue', 'storefront'));
    }
}
