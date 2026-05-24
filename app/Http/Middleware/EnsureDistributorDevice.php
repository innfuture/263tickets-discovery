<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\DistributorDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the POS terminal calling the distributor sales API from a
 * paired bearer token, attaches the device + its parent distributor
 * to the request, and updates last_seen telemetry.
 *
 * Token format: `dpt_<plaintext>` (mirror of the developer-portal
 * token pattern). Stored as sha256(plaintext) on the device row.
 */
class EnsureDistributorDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->bearerToken();
        if ($token === '') {
            return response()->json(['error' => 'missing_token'], 401);
        }

        $hash = hash('sha256', $token);
        $device = DistributorDevice::query()
            ->where('pairing_token_hash', $hash)
            ->where('status', DistributorDevice::STATUS_ACTIVE)
            ->with('distributor')
            ->first();

        if (! $device) {
            return response()->json(['error' => 'invalid_token'], 401);
        }
        if (! $device->paired_at) {
            return response()->json(['error' => 'device_not_paired'], 401);
        }

        $device->forceFill([
            'last_seen_at' => now(),
            'last_seen_ip' => $request->ip(),
        ])->save();

        $request->attributes->set('distributor_device', $device);
        $request->attributes->set('distributor', $device->distributor);

        return $next($request);
    }
}
