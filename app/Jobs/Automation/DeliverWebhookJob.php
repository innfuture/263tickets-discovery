<?php

declare(strict_types=1);

namespace App\Jobs\Automation;

use App\Models\OrganizationWebhook;
use App\Services\Automation\WebhookSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Queued POST to an `organization_webhooks.url`. Logs every attempt
 * into `organization_webhook_deliveries`. Retry envelope mirrors the
 * scanner webhook backoff: [1, 5, 30, 300, 1800] seconds.
 *
 * Body shape:
 *   {
 *     "id": "<delivery uuid>",
 *     "type": "order.paid",
 *     "created": <unix>,
 *     "data": { … event-specific payload … }
 *   }
 *
 * Signature in `X-Example-App-Signature` header, t=<unix>,v1=<hex>.
 * Receiver verifies by recomputing the HMAC over `<t>.<rawBody>`.
 */
class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 6;

    /** @var array<int, int> Backoff schedule in seconds, indexed by attempt-1. */
    protected array $backoffSchedule = [1, 5, 30, 300, 1800];

    public function __construct(
        public int $webhookId,
        public string $eventType,
        public array $payload,
        public int $attempt = 1,
        public ?string $deliveryId = null,
    ) {
        // Stable across retries — receivers dedupe on body.id, so a
        // fresh UUID per attempt would defeat their idempotency.
        $this->deliveryId ??= (string) Str::uuid();
    }

    public function backoff(): array
    {
        return $this->backoffSchedule;
    }

    public function handle(WebhookSigner $signer): void
    {
        $hook = OrganizationWebhook::query()->find($this->webhookId);
        if (! $hook || ! $hook->is_active) {
            return;
        }

        $body = [
            'id' => $this->deliveryId,
            'type' => $this->eventType,
            'created' => time(),
            'data' => $this->payload,
        ];
        $raw = (string) json_encode($body, JSON_UNESCAPED_SLASHES);
        $signature = $signer->sign($raw, (string) $hook->signing_secret);

        $started = microtime(true);
        $statusCode = null;
        $responseBody = null;
        $error = null;

        try {
            $response = Http::timeout((int) config('automation.timeout_seconds', 10))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    WebhookSigner::HEADER => $signature,
                    'X-Example-App-Event' => $this->eventType,
                    'User-Agent' => 'example-app-automations/1.0',
                ])
                ->withBody($raw, 'application/json')
                ->post((string) $hook->url);

            $statusCode = $response->status();
            $responseBody = substr((string) $response->body(), 0, 4_000);
        } catch (\Throwable $e) {
            $error = substr($e->getMessage(), 0, 1_000);
        }

        $durationMs = (int) ((microtime(true) - $started) * 1000);

        DB::table('organization_webhook_deliveries')->insert([
            'organization_webhook_id' => $hook->id,
            'event' => $this->eventType,
            'status_code' => $statusCode,
            'attempt' => $this->attempt,
            'duration_ms' => $durationMs,
            'payload' => $raw,
            'response_body' => $responseBody ?? $error,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $delivered = $statusCode !== null && $statusCode >= 200 && $statusCode < 300;

        if ($delivered) {
            $hook->forceFill(['last_delivered_at' => Carbon::now()])->save();

            return;
        }

        Log::warning('automation.webhook.delivery_failed', [
            'webhook_id' => $hook->id,
            'event' => $this->eventType,
            'attempt' => $this->attempt,
            'status' => $statusCode,
            'error' => $error,
        ]);

        // Manual retry — gives us per-attempt rows in the deliveries
        // table without relying on the queue worker's retry semantics.
        $retryLimit = max(1, (int) ($hook->retry_limit ?? 5));
        if ($this->attempt < $retryLimit && isset($this->backoffSchedule[$this->attempt - 1])) {
            $delay = $this->backoffSchedule[$this->attempt - 1];
            self::dispatch(
                $this->webhookId,
                $this->eventType,
                $this->payload,
                $this->attempt + 1,
                $this->deliveryId,
            )
                ->onQueue($this->queue ?? 'automations')
                ->delay($delay);
        }
    }
}
