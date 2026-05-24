<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Read-only request router for multi-region active-active deploys.
 *
 * Inspects `CF-IPCountry` (or `X-Region` for non-CF deploys) and
 * sets the active read connection to the nearest configured replica.
 * Writes always go to the primary — Laravel's read/write split on
 * the connection handles that automatically.
 *
 * Apply only to GET/HEAD endpoints that don't write:
 *
 *   Route::middleware('regional.read')->group(function () {
 *       Route::get('events', ...);
 *       Route::get('events/{slug}', ...);
 *   });
 *
 * Falls through silently when the region map isn't configured or
 * the connection doesn't exist — single-region deploys are unaffected.
 */
class RouteToRegionalReadReplica
{
    public function handle(Request $request, Closure $next): Response
    {
        $region = $this->resolveRegion($request);
        $map = (array) config('database.region_routing', []);

        if ($region === null || empty($map)) {
            return $next($request);
        }

        $connection = $map[$region] ?? $map['_default'] ?? null;
        if (! is_string($connection) || ! Config::has("database.connections.{$connection}")) {
            return $next($request);
        }

        // Route the default connection's reads to the regional replica.
        // Writes still flow to the primary via the connection's `write` config.
        Config::set('database.default', $connection);
        DB::purge();

        return $next($request);
    }

    protected function resolveRegion(Request $request): ?string
    {
        $country = $request->header('CF-IPCountry')
            ?? $request->header('X-Country')
            ?? null;
        if (is_string($country) && strlen($country) === 2) {
            return strtoupper($country);
        }

        $explicit = $request->header('X-Region');
        if (is_string($explicit)) {
            return strtoupper($explicit);
        }

        return null;
    }
}
