<?php

declare(strict_types=1);

namespace App\Services\EventBus\Contracts;

/**
 * Cross-process domain event abstraction. Today our domain events
 * (OrderPaid, TicketScanned, EventInventoryChanged) fan out via
 * Laravel's event system — fine while every consumer lives in the
 * same PHP process. As soon as we want a separate analytics worker
 * or a Go scanner-edge service to react, we need a real message bus.
 *
 * `LaravelEventBus` is the default; `RedisStreamBus` is the drop-in
 * upgrade once the deployment has Redis. Both publish the SAME shape
 * so a consumer doesn't care which one's wired.
 *
 * Payload shape:
 *   {
 *     "id": "<uuid>",
 *     "type": "order.paid",
 *     "occurred_at": <iso8601>,
 *     "organisation_id": "<uuid>",
 *     "data": { ... }
 *   }
 */
interface DomainBus
{
    public function publish(string $eventType, string $organisationUuid, array $payload): void;
}
