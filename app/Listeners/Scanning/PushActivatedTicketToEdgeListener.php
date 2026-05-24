<?php

declare(strict_types=1);

namespace App\Listeners\Scanning;

use App\Events\TicketActivated;
use App\Jobs\Scanning\PushActivatedTicketToEdgeJob;
use Carbon\CarbonImmutable;

/**
 * Observes TicketActivated and dispatches the edge-push job, gated
 * by the activation horizon — tickets for events beyond the horizon
 * don't get pushed so the edge KV stays bounded.
 */
class PushActivatedTicketToEdgeListener
{
    public function handle(TicketActivated $event): void
    {
        $ticket = $event->ticket;
        $uuid = (string) ($ticket->uuid ?? '');
        if ($uuid === '') {
            return;
        }

        $horizonHours = (int) config('distribution.edge.activation_horizon_hours', 48);
        $ticket->loadMissing('event');
        $eventStart = $ticket->event?->starts_at;
        if ($eventStart) {
            $start = CarbonImmutable::parse($eventStart);
            if ($start->diffInHours(CarbonImmutable::now()) > $horizonHours && $start->isFuture()) {
                return;
            }
        }

        PushActivatedTicketToEdgeJob::dispatch($uuid, $horizonHours * 3600);
    }
}
