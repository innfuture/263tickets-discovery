<?php

declare(strict_types=1);

namespace App\Services\Payments\Support;

use App\Services\Payments\Data\ChargeResult;
use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

/**
 * Caches the result of a payment charge under a caller-supplied
 * idempotency key so the same request retried within the TTL returns
 * the original ChargeResult instead of double-charging.
 *
 * Production gateways need this any time the network path is unreliable
 * — n8n retries, FE form double-submit, mobile-app reconnect mid-call.
 * The sandbox driver already has its own idempotency table; this is the
 * cross-gateway equivalent for real money flows.
 */
class IdempotencyCache
{
    public function __construct(
        protected ?CacheRepository $store = null,
        protected int $ttlSeconds = 86400,
    ) {}

    public static function default(): self
    {
        $store = (string) config('payments.idempotency.cache_store', '');
        $ttl = (int) config('payments.idempotency.ttl_seconds', 86400);

        return new self(
            $store !== '' ? Cache::store($store) : Cache::store(),
            $ttl > 0 ? $ttl : 86400,
        );
    }

    /**
     * Look up a prior ChargeResult, or compute + cache one.
     *
     * @param  Closure(): ChargeResult  $compute
     */
    public function remember(string $gateway, string $key, Closure $compute): ChargeResult
    {
        $cacheKey = $this->cacheKey($gateway, $key);
        $cached = $this->store?->get($cacheKey);
        if ($cached instanceof ChargeResult) {
            return $cached;
        }

        $result = $compute();
        $this->store?->put($cacheKey, $result, $this->ttlSeconds);

        return $result;
    }

    protected function cacheKey(string $gateway, string $key): string
    {
        return 'payments:idem:'.$gateway.':'.hash('sha256', $key);
    }
}
