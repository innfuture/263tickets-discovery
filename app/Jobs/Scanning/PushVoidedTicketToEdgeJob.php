<?php

declare(strict_types=1);

namespace App\Jobs\Scanning;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Upserts a ticket UUID into the scanner-edge VOIDED_TICKETS KV
 * namespace via the Cloudflare REST API. The edge worker reads this
 * KV on every scan and denies the ticket without an origin round trip.
 *
 * No-op (and logs nothing) when KV is not configured — keeps local
 * dev / CI / single-region deployments happy.
 *
 * Retries with backoff; KV writes are eventually consistent so we
 * don't need to be aggressive.
 */
class PushVoidedTicketToEdgeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @var array<int> */
    public array $backoff = [5, 15, 60, 300, 900];

    public function __construct(
        public readonly string $ticketUuid,
        public readonly string $reason,
    ) {}

    public function handle(): void
    {
        $accountId = (string) config('scanning.edge.kv.account_id', '');
        $namespaceId = (string) config('scanning.edge.kv.namespace_id', '');
        $apiToken = (string) config('scanning.edge.kv.api_token', '');

        if ($accountId === '' || $namespaceId === '' || $apiToken === '') {
            return;
        }

        $endpoint = "https://api.cloudflare.com/client/v4/accounts/{$accountId}"
            ."/storage/kv/namespaces/{$namespaceId}/values/".rawurlencode($this->ticketUuid);

        $response = Http::withToken($apiToken)
            ->withHeaders(['Content-Type' => 'text/plain'])
            ->timeout(10)
            ->put($endpoint, $this->reason);

        if (! $response->successful()) {
            Log::warning('scanning.edge.void_push_failed', [
                'ticket_uuid' => $this->ticketUuid,
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);
            $this->release($this->backoff[$this->attempts() - 1] ?? 900);
        }
    }
}
