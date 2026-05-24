<?php

declare(strict_types=1);

namespace App\Listeners\Automation;

use App\Events\TicketScanned;
use App\Models\Organization;
use App\Services\Automation\AutomationDispatcher;

/**
 * TicketScanned → `ticket.scanned` webhook. Door analytics, fraud
 * alerts, "welcome — here's the venue WiFi" workflows.
 */
class PublishTicketScanned
{
    public function __construct(protected AutomationDispatcher $dispatcher) {}

    public function handle(TicketScanned $event): void
    {
        $scan = $event->scanEvent ?? null;
        if (! $scan) {
            return;
        }

        $org = Organization::query()->where('uuid', $scan->organisation_id ?? '')->first();
        if (! $org) {
            return;
        }

        $this->dispatcher->dispatch('ticket.scanned', $org, [
            'scan_uuid' => $scan->uuid ?? null,
            'verdict' => $scan->verdict ?? null,
            'reason_code' => $scan->reason_code ?? null,
            'flags' => (array) ($scan->flags ?? []),
            'ticket_uuid' => $scan->ticket_uuid ?? null,
            'event_id' => $scan->event_id ?? null,
            'device_id' => $scan->device_id ?? null,
            'profile_id' => $scan->profile_id ?? null,
            'server_at' => optional($scan->server_at)->toIso8601String(),
        ]);
    }
}
