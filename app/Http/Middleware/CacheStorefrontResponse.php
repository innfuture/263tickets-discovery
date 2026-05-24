<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamps a `Cache-Control: public, max-age=N` header on storefront
 * discovery responses so the storefront-edge worker (and any
 * upstream CDN) caches them for the configured TTL. Skipped on
 * authenticated requests + on responses that already set their own
 * Cache-Control (e.g. cached-by-the-app responses from
 * EventCatalogController).
 *
 * Pair this with SignStorefrontResponse — it's the second half of
 * the edge-cacheable contract.
 */
class CacheStorefrontResponse
{
    public function handle(Request $request, Closure $next, ?string $maxAge = null): Response
    {
        $response = $next($request);

        // Already set by the caller — respect it.
        if ($response->headers->has('Cache-Control') && $response->headers->get('Cache-Control') !== '') {
            return $response;
        }

        // Only cache successful GETs.
        if ($request->method() !== 'GET' || $response->getStatusCode() !== 200) {
            return $response;
        }

        $ttl = (int) ($maxAge ?? config('storefront.discovery.cache_control_max_age', 30));
        if ($ttl <= 0) {
            return $response;
        }

        $response->headers->set('Cache-Control', "public, max-age={$ttl}, s-maxage={$ttl}");
        $response->headers->set('Vary', 'CF-IPCountry, Accept-Language, Accept-Encoding');

        return $response;
    }
}
