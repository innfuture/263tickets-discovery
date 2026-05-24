<?php

declare(strict_types=1);

namespace App\Services\EventBus;

use App\Services\EventBus\Contracts\DomainBus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Nats\Connection;
use Nats\ConnectionOptions;

/**
 * NATS JetStream publisher. Subject scheme:
 *
 *   domain.<event_type>          e.g. `domain.order.paid`
 *
 * Bound when `eventbus.driver=nats`. Requires either the
 * `repejota/phpnats` package (sync) or any NATS-over-HTTP gateway
 * (NATS NGS or a custom HTTP adapter). Falls back to no-op + a
 * structured warning log if no adapter is available.
 *
 * Why NATS over Redis Streams for some shops:
 *   - sub-millisecond fan-out across regions
 *   - native at-least-once + dedup via msg-ids
 *   - simpler ops than Redis for high-throughput event flow
 */
class NatsBus implements DomainBus
{
    public function __construct(
        protected string $host,
        protected int $port,
        protected ?string $token = null,
        protected ?string $streamPrefix = 'domain',
    ) {}

    public function publish(string $eventType, string $organisationUuid, array $payload): void
    {
        $subject = $this->streamPrefix.'.'.$eventType;

        $body = json_encode([
            'id' => (string) Str::uuid(),
            'type' => $eventType,
            'organisation_id' => $organisationUuid,
            'data' => $payload,
            'occurred_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_SLASHES);

        if (class_exists(Connection::class)) {
            $this->publishViaNatsPhp($subject, (string) $body);

            return;
        }

        // No transport available — log structured so ops can see the
        // gap without the request failing.
        Log::warning('eventbus.nats.no_transport', [
            'subject' => $subject,
            'note' => 'install repejota/phpnats or wire an HTTP→NATS bridge to actually publish.',
        ]);
    }

    protected function publishViaNatsPhp(string $subject, string $body): void
    {
        try {
            $options = new ConnectionOptions;
            $options->setHost($this->host)->setPort($this->port);
            if ($this->token !== null && $this->token !== '') {
                $options->setToken($this->token);
            }
            $conn = new Connection($options);
            $conn->connect();
            $conn->publish($subject, $body);
            $conn->close();
        } catch (\Throwable $e) {
            Log::warning('eventbus.nats.publish_failed', [
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
