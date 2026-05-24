<?php

declare(strict_types=1);

namespace App\Listeners\Storefront;

use App\Events\CapacityReleased;
use App\Jobs\Storefront\NotifyWaitlistOnCapacityReleasedJob;

/**
 * Bridges CapacityReleased into the waitlist notification job. Thin —
 * the actual notification logic lives in the job so concurrent
 * releases are serialised by the queue.
 */
class NotifyWaitlistOnRelease
{
    public function handle(CapacityReleased $event): void
    {
        $eventId = (int) ($event->session->event_id ?? 0);
        if ($eventId === 0) {
            return;
        }

        NotifyWaitlistOnCapacityReleasedJob::dispatch($eventId)
            ->onQueue('storefront');
    }
}
