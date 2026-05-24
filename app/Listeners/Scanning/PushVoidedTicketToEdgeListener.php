<?php

declare(strict_types=1);

namespace App\Listeners\Scanning;

use App\Events\TicketVoided;
use App\Jobs\Scanning\PushVoidedTicketToEdgeJob;

/**
 * Observer for TicketVoided. Dispatches the edge-push job so the
 * scanner-edge worker can deny the voided ticket on its next scan
 * attempt without an origin round-trip.
 */
class PushVoidedTicketToEdgeListener
{
    public function handle(TicketVoided $event): void
    {
        $uuid = (string) ($event->ticket->uuid ?? '');
        if ($uuid === '') {
            return;
        }

        PushVoidedTicketToEdgeJob::dispatch($uuid, $event->reason);
    }
}
