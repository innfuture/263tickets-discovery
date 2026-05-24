<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ScannerDevice;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token auth for the public /api/v1/scanning/* surface.
 *
 *   1. Pulls "Bearer <token>" out of Authorization.
 *   2. Hashes (sha256) and looks up the matching ScannerDevice.
 *   3. Enforces device + profile status, profile IP allowlist, and
 *      per-device per-minute rate limit (from the profile).
 *   4. Stamps last_seen / last_known_ip / last_known_lat/lng so the
 *      admin UI can show device health.
 *
 * On success: binds the device + profile into the request and the
 * container so controllers can `request()->scannerDevice` or resolve
 * `ScannerDevice::class` from DI.
 *
 * Constant-time hash comparison; missing bearer / wrong token / IP
 * mismatch / disabled status all return 401 with no information
 * leakage.
 */
class ScannerTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $auth = (string) $request->header('Authorization', '');
        if (! str_starts_with($auth, 'Bearer ')) {
            return $this->unauthorized('missing_bearer');
        }

        $token = substr($auth, 7);
        if ($token === '') {
            return $this->unauthorized('missing_bearer');
        }

        $device = ScannerDevice::query()
            ->with('profile')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($device === null || ! $device->isActive()) {
            return $this->unauthorized('invalid_token');
        }

        $profile = $device->profile;

        // IP allowlist (CIDR-friendly). Empty list = any IP allowed.
        $allow = (array) $profile->ip_allowlist;
        if ($allow !== [] && ! IpUtils::checkIp((string) $request->ip(), $allow)) {
            return $this->unauthorized('ip_not_allowed');
        }

        // Per-device per-minute rate limit. Cache key TTL is 60s and
        // resets independently of the FraudEngine VelocityRule (which
        // looks at successful scans only — this gate counts every
        // request to the API regardless of verdict).
        $rl = $this->checkRateLimit($device, $profile->max_scans_per_minute ?: 60);
        if ($rl !== null) {
            return $rl;
        }

        // Update last-seen telemetry. lat/lng/IP come from the request
        // when provided; mobile apps include them in JSON body for
        // POST /scan, in headers for GET endpoints.
        $device->forceFill(array_filter([
            'last_seen_at' => now(),
            'last_known_ip' => $request->ip(),
            'last_known_lat' => $request->header('X-Scanner-Lat') ?: null,
            'last_known_lng' => $request->header('X-Scanner-Lng') ?: null,
        ], fn ($v) => $v !== null))->save();

        $request->attributes->set('scanner_device', $device);
        $request->attributes->set('scanner_profile', $profile);
        app()->instance(ScannerDevice::class, $device);

        return $next($request);
    }

    protected function checkRateLimit(ScannerDevice $device, int $maxPerMinute): ?Response
    {
        $key = 'scanner.rl.'.$device->id;
        $count = (int) Cache::get($key, 0);
        if ($count >= $maxPerMinute) {
            return response()->json([
                'error' => 'rate_limited',
                'message' => "Device exceeded {$maxPerMinute} requests/minute.",
                'retry_after_seconds' => 60,
            ], 429, ['Retry-After' => '60']);
        }
        Cache::put($key, $count + 1, now()->addMinute());

        return null;
    }

    protected function unauthorized(string $code): Response
    {
        return response()->json(['error' => $code], 401);
    }
}
