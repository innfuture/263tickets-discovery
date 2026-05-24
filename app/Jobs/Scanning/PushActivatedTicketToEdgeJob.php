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
 * Mirror of PushVoidedTicketToEdgeJob — upserts an "activated" entry
 * into the scanner-edge ACTIVATED_TICKETS KV namespace so the edge
 * can answer scan attempts positively without an origin RTT.
 *
 * No-op when KV is unconfigured. Bounded TTL (default 48h) keeps the
 * KV size manageable: only tickets for events within the activation
 * horizon get pushed.
 */
class PushActivatedTicketToEdgeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [5, 15, 60, 300, 900];

    public function __construct(
        public readonly string $ticketUuid,
        public readonly int $ttlSeconds = 172800,
    ) {}

    public function handle(): void
    {
        if (! config('distribution.edge.push_activations_to_edge', false)) {
            return;
        }

        $accountId = (string) config('scanning.edge.kv.account_id', '');
        $namespaceId = (string) config('scanning.edge.kv.activated_namespace_id', '');
        $apiToken = (string) config('scanning.edge.kv.api_token', '');

        if ($accountId === '' || $namespaceId === '' || $apiToken === '') {
            return;
        }

        $endpoint = "https://api.cloudflare.com/client/v4/accounts/{$accountId}"
            ."/storage/kv/namespaces/{$namespaceId}/values/".rawurlencode($this->ticketUuid)
            ."?expiration_ttl={$this->ttlSeconds}";

        $response = Http::withToken($apiToken)
            ->withHeaders(['Content-Type' => 'text/plain'])
            ->timeout(10)
            ->put($endpoint, 'activated');

        if (! $response->successful()) {
            Log::warning('distribution.edge.activation_push_failed', [
                'ticket_uuid' => $this->ticketUuid,
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);
            $this->release($this->backoff[$this->attempts() - 1] ?? 900);
        }
    }
}
