<?php

declare(strict_types=1);

namespace App\Services\EventBus;

use App\Services\EventBus\Contracts\DomainBus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Redis Streams (XADD) publisher. One stream per event type so
 * consumers can use the native consumer-group semantics for
 * partitioned, at-least-once delivery:
 *
 *   XADD domain.order.paid * id <uuid> type order.paid org <uuid> data <json>
 *
 * Out-of-band consumers (Go scanner-edge, Python analytics worker,
 * etc.) use `XREADGROUP` to pull. PHP listeners still get fired
 * in-process too — the bus is additive, not a replacement.
 *
 * Wire via `EVENTBUS_DRIVER=redis_streams` once your deployment has
 * Redis (Laravel ships with the `redis` extension wrapper).
 */
class RedisStreamBus implements DomainBus
{
    public function __construct(
        protected string $connection = 'default',
        protected int $maxLen = 100_000,
    ) {}

    public function publish(string $eventType, string $organisationUuid, array $payload): void
    {
        $streamKey = "domain.{$eventType}";

        try {
            Redis::connection($this->connection)->command('XADD', [
                $streamKey,
                ['MAXLEN', '~', (string) $this->maxLen],
                '*', // auto-generate ID
                'id', (string) Str::uuid(),
                'type', $eventType,
                'organisation_id', $organisationUuid,
                'occurred_at', now()->toIso8601String(),
                'data', (string) json_encode($payload, JSON_UNESCAPED_SLASHES),
            ]);
        } catch (\Throwable $e) {
            // Never let the bus take down the request — log and move on.
            // Consumers can read a delivery-failure budget from the
            // metrics emitted alongside.
            Log::warning('eventbus.redis.publish_failed', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
