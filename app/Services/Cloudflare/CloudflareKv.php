<?php

declare(strict_types=1);

namespace App\Services\Cloudflare;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around Cloudflare's KV REST API. Used by the
 * `scanner-edge` Worker to read voided-ticket UUIDs without hitting
 * origin. Origin pushes here whenever an OfflineTicket is voided
 * (see CloudflareKvSyncObserver).
 *
 * Config: storefront.cloudflare.{account_id, api_token, voided_namespace_id}.
 * When any of those are missing, the put/delete calls log a structured
 * "skipped" line and no-op so the storefront doesn't crash without CF.
 */
class CloudflareKv
{
    public function __construct(
        protected ?string $accountId,
        protected ?string $apiToken,
        protected int $timeoutSeconds = 5,
    ) {}

    public function put(string $namespaceId, string $key, string $value, ?int $ttlSeconds = null): bool
    {
        if (! $this->configured() || $namespaceId === '') {
            Log::info('cloudflare.kv.put.skipped', [
                'reason' => 'cloudflare KV not configured',
                'key' => $key,
            ]);

            return false;
        }

        try {
            $url = $this->endpoint($namespaceId, $key, $ttlSeconds);
            $response = Http::withToken($this->apiToken)
                ->timeout($this->timeoutSeconds)
                ->withBody($value, 'text/plain')
                ->put($url);

            if ($response->successful()) {
                return true;
            }

            Log::warning('cloudflare.kv.put.failed', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
                'key' => $key,
            ]);
        } catch (\Throwable $e) {
            Log::warning('cloudflare.kv.put.exception', [
                'message' => $e->getMessage(),
                'key' => $key,
            ]);
        }

        return false;
    }

    public function delete(string $namespaceId, string $key): bool
    {
        if (! $this->configured() || $namespaceId === '') {
            return false;
        }

        try {
            $url = $this->endpoint($namespaceId, $key, null);
            $response = Http::withToken($this->apiToken)
                ->timeout($this->timeoutSeconds)
                ->delete($url);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('cloudflare.kv.delete.exception', [
                'message' => $e->getMessage(),
                'key' => $key,
            ]);

            return false;
        }
    }

    protected function configured(): bool
    {
        return ! empty($this->accountId) && ! empty($this->apiToken);
    }

    protected function endpoint(string $namespaceId, string $key, ?int $ttl): string
    {
        $url = sprintf(
            'https://api.cloudflare.com/client/v4/accounts/%s/storage/kv/namespaces/%s/values/%s',
            urlencode((string) $this->accountId),
            urlencode($namespaceId),
            rawurlencode($key),
        );

        return $ttl !== null && $ttl > 0
            ? $url.'?expiration_ttl='.$ttl
            : $url;
    }
}
