<?php

declare(strict_types=1);

namespace App\Jobs\Scanning;

use App\Models\ScanEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Delivers one scan event to the scanner profile's configured webhook
 * URL with an HMAC-SHA256 signature so the receiver can verify the
 * payload came from us. Retries on failure following the schedule in
 * config('scanning.webhooks.retries').
 *
 * Same signing scheme as the Stripe-style payment webhooks for
 * operational consistency:
 *   X-Scanner-Signature: t=<unix>,v1=<hex hmac of "t.body">
 */
class DeliverScanWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 6;

    public function __construct(public int $scanEventId) {}

    public function backoff(): array
    {
        return (array) config('scanning.webhooks.retries', [1, 5, 30, 300, 1800]);
    }

    public function handle(): void
    {
        $scan = ScanEvent::with(['profile', 'event', 'ticket'])->find($this->scanEventId);
        if ($scan === null) {
            return;
        }

        $profile = $scan->profile;
        if ($profile === null || empty($profile->webhook_url) || empty($profile->webhook_secret)) {
            return;
        }

        $body = (string) json_encode($this->payload($scan), JSON_UNESCAPED_SLASHES);
        $ts = (string) time();
        $sig = hash_hmac('sha256', "{$ts}.{$body}", (string) $profile->webhook_secret);

        try {
            $response = Http::timeout((int) config('scanning.webhooks.timeout_seconds', 5))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Scanner-Signature' => "t={$ts},v1={$sig}",
                    'X-Scanner-Event' => 'ticket.scanned',
                    'User-Agent' => 'example-app-scanner/1.0',
                ])
                ->withBody($body, 'application/json')
                ->post((string) $profile->webhook_url);

            if (! $response->successful()) {
                throw new \RuntimeException("HTTP {$response->status()} from webhook receiver.");
            }
        } catch (\Throwable $e) {
            Log::warning('scanner.webhook.delivery_failed', [
                'scan_event' => $scan->uuid,
                'profile' => $profile->uuid,
                'attempt' => $this->attempts(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(ScanEvent $scan): array
    {
        return [
            'event' => 'ticket.scanned',
            'scan' => [
                'uuid' => $scan->uuid,
                'verdict' => $scan->verdict,
                'reason_code' => $scan->reason_code,
                'flags' => $scan->fraud_flags,
                'was_admitted' => (bool) $scan->was_admitted,
                'was_duplicate' => (bool) $scan->was_duplicate,
                'was_voided' => (bool) $scan->was_voided,
                'payload' => $scan->payload,
                'client_lat' => $scan->client_lat,
                'client_lng' => $scan->client_lng,
                'client_at' => $scan->client_at?->toIso8601String(),
                'server_at' => $scan->created_at?->toIso8601String(),
                'latency_ms' => (int) $scan->latency_ms,
            ],
            'event_ref' => $scan->event ? [
                'id' => (int) $scan->event->id,
                'slug' => $scan->event->slug,
                'name' => $scan->event->name,
            ] : null,
            'ticket' => $scan->ticket ? [
                'ticket_number' => $scan->ticket->ticket_number,
                'scan_count' => (int) $scan->ticket->scan_count,
            ] : null,
            'device_uuid' => $scan->device?->uuid,
            'profile_uuid' => $scan->profile->uuid,
        ];
    }
}
