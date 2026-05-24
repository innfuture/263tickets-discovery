<?php

declare(strict_types=1);

namespace App\Services\EventBus;

use App\Services\EventBus\Contracts\DomainBus;
use Illuminate\Support\Facades\Event;

/**
 * Default DomainBus impl — just fires a Laravel event keyed by event
 * type. Existing in-process listeners (PublishOrderPaid, etc.) bind
 * to the typed class events; this layer is for any out-of-process
 * subscriber that doesn't want a typed PHP listener.
 *
 * Event name format: `domain.<event_type>` so a wildcard listener
 * (`Event::listen('domain.*', ...)`) can subscribe to all of them.
 */
class LaravelEventBus implements DomainBus
{
    public function publish(string $eventType, string $organisationUuid, array $payload): void
    {
        Event::dispatch("domain.{$eventType}", [[
            'type' => $eventType,
            'organisation_id' => $organisationUuid,
            'data' => $payload,
            'occurred_at' => now()->toIso8601String(),
        ]]);
    }
}
