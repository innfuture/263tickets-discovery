<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Scanning;

use App\Http\Controllers\Controller;
use App\Services\Scanning\PairingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exchange a one-time pairing code (issued from the Scanners admin
 * page) for a long-lived device token + the device's capabilities.
 *
 * Returns the plaintext token ONCE. The server stores only sha256.
 * The mobile app must persist the token in the OS keychain.
 *
 * POST /api/v1/scanning/pair
 *   { "code": "ABC23XYZ", "device_label": "John's iPhone",
 *     "platform": "ios", "app_version": "1.0.4", "hardware_id": "…" }
 *
 *   200 { "token": "scn_…", "device": {...}, "profile": {...} }
 *   422 { "error": "invalid_code", "message": "…" }
 */
class PairingController extends Controller
{
    public function __construct(protected PairingService $pairing) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:8'],
            'device_label' => ['required', 'string', 'max:120'],
            'platform' => ['nullable', 'in:ios,android,web,other'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'hardware_id' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $result = $this->pairing->redeem($data['code'], $data);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'invalid_code', 'message' => $e->getMessage()], 422);
        }

        $device = $result['device'];
        $profile = $device->profile;

        return response()->json([
            'token' => $result['token'],
            'device' => [
                'uuid' => $device->uuid,
                'label' => $device->device_label,
                'platform' => $device->platform,
            ],
            'profile' => [
                'uuid' => $profile->uuid,
                'name' => $profile->name,
                'capabilities' => (array) $profile->capabilities,
                'allowed_event_ids' => (array) $profile->allowed_event_ids,
                'max_scans_per_minute' => $profile->max_scans_per_minute,
                'duplicate_window_seconds' => $profile->duplicate_window_seconds,
            ],
        ], 200);
    }
}
