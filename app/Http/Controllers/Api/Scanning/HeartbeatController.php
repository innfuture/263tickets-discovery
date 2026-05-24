<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Scanning;

use App\Http\Controllers\Controller;
use App\Models\ScannerDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/v1/scanning/heartbeat
 *
 * Lightweight check-in: updates last_seen + optional lat/lng/battery
 * telemetry. Mobile app calls this every 30-60s while idle so the
 * admin UI can show device health.
 *
 * Returns the server's `revoked` flag — if true, the app should drop
 * its cached token and surface a "this device was revoked" screen.
 * That's the canonical way an organizer kills a lost / stolen device.
 */
class HeartbeatController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'battery' => ['nullable', 'numeric', 'between:0,1'],
            'connection' => ['nullable', 'string', 'max:20'],
        ]);

        /** @var ScannerDevice $device */
        $device = $request->attributes->get('scanner_device');

        $device->forceFill(array_filter([
            'last_known_lat' => $data['lat'] ?? null,
            'last_known_lng' => $data['lng'] ?? null,
            'last_known_ip' => $request->ip(),
            'last_seen_at' => now(),
        ], fn ($v) => $v !== null))->save();

        return response()->json([
            'ok' => true,
            'revoked' => $device->status !== 'active' || $device->profile->status !== 'active',
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
