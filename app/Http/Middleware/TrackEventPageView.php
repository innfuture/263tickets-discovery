<?php

namespace App\Http\Middleware;

use App\Models\EventPageView;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Silently records a page_view row when an event's show route is hit.
 * Runs after the response is sent (terminable) to avoid adding latency.
 */
class TrackEventPageView
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $response->isSuccessful()) {
            return;
        }

        // Only track the event show page
        $eventId = $request->route('event')?->getKey();
        if (! $eventId) {
            return;
        }

        try {
            EventPageView::create([
                'event_id' => $eventId,
                'session_id' => substr(session()->getId() ?? '', 0, 64),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'referrer' => $request->headers->get('Referer')
                    ? substr($request->headers->get('Referer'), 0, 2048)
                    : null,
                'country_code' => self::detectCountry($request),
                'utm_source' => $request->query('utm_source'),
                'utm_medium' => $request->query('utm_medium'),
                'utm_campaign' => $request->query('utm_campaign'),
                'utm_term' => $request->query('utm_term'),
                'utm_content' => $request->query('utm_content'),
                'event_type' => 'page_view',
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Never throw from tracking — it must be invisible to the user.
        }
    }

    /**
     * Read the visitor's country from common edge-network headers.
     *
     * Headers are only trusted when:
     *  - `TRUST_GEO_HEADERS=true` is set in the environment (explicit opt-in
     *    for deployments behind a known CDN like Cloudflare/Vercel), OR
     *  - the request came through a Laravel-configured trusted proxy.
     *
     * Without this guard, any anonymous visitor could spoof a `CF-IPCountry`
     * header and pollute analytics.
     */
    public static function detectCountry(Request $request): ?string
    {
        if (! self::shouldTrustGeoHeaders($request)) {
            return null;
        }

        $candidates = [
            'CF-IPCountry',          // Cloudflare
            'X-AppEngine-Country',   // Google App Engine
            'X-Vercel-IP-Country',   // Vercel
            'X-Country-Code',        // generic proxy convention
            'X-Geo-Country',         // some reverse proxies
        ];

        foreach ($candidates as $header) {
            $value = $request->headers->get($header);
            if ($value && strlen($value) === 2 && ctype_alpha($value)) {
                return strtoupper($value);
            }
        }

        return null;
    }

    private static function shouldTrustGeoHeaders(Request $request): bool
    {
        if (filter_var(env('TRUST_GEO_HEADERS', false), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        // Laravel's TrustProxies middleware sets this when the request was
        // forwarded by a proxy listed in config/trustedproxy.php.
        return $request->isFromTrustedProxy();
    }
}
