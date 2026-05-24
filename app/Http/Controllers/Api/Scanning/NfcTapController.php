<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Scanning;

use App\Http\Controllers\Controller;
use App\Services\Scanning\Nfc\Exceptions\NfcVerificationException;
use App\Services\Scanning\Nfc\NfcVerificationService;
use App\Services\Scanning\ScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 *   POST /api/v1/scanning/nfc-tap
 *
 * Bearer-token authenticated like the rest of the scanner API.
 * Flow:
 *
 *   1. Decode + verify the NFC payload with the configured provider.
 *      Returns the underlying ticket's QR-equivalent payload.
 *   2. Hand the payload to the existing ScanService so the fraud
 *      pipeline (Velocity, Geofence, OffPeak, Voided, …) and audit
 *      trail run exactly as they would for a QR scan.
 *
 * The verdict shape is identical to /scan so the scanner app can use
 * one code path on the client.
 */
class NfcTapController extends Controller
{
    public function __construct(
        protected NfcVerificationService $nfc,
        protected ScanService $scans,
    ) {}

    public function tap(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'in:apple_vas,google_smart_tap,stub'],
            'payload' => ['required', 'string', 'max:8000'],
            'client_lat' => ['nullable', 'numeric'],
            'client_lng' => ['nullable', 'numeric'],
            'device_meta' => ['nullable', 'array'],
        ]);

        $device = $request->attributes->get('scanner_device');

        try {
            $verification = $this->nfc->verify(
                provider: $validated['provider'],
                encodedPayload: $validated['payload'],
                context: ['scanner_device_id' => $device?->id],
            );
        } catch (NfcVerificationException $e) {
            return response()->json([
                'verdict' => 'deny',
                'reason_code' => $e->reasonCode,
                'message' => $e->getMessage(),
                'channel' => 'nfc',
            ], 422);
        }

        // Defer the rest (fraud rules, duplicate-window, voided check,
        // ScanEvent persistence) to the existing ScanService by feeding
        // it the QR-equivalent payload.
        $result = $this->scans->scan([
            'device' => $device,
            'profile' => $device?->profile,
            'payload' => (string) $verification->offlineTicket?->qr_payload,
            'client_lat' => $validated['client_lat'] ?? null,
            'client_lng' => $validated['client_lng'] ?? null,
            'client_ip' => $request->ip(),
            'device_meta' => $validated['device_meta'] ?? [],
            'channel' => 'nfc',
        ]);

        // Cross-link the NfcVerification row to whichever ScanEvent
        // the ScanService created so audits can join the two.
        if (! empty($result['scan_uuid'])) {
            $verification->forceFill([
                'scan_event_id' => $result['scan_event_id'] ?? null,
                'verdict' => $result['verdict'] ?? $verification->verdict,
                'reason_code' => $result['reason_code'] ?? $verification->reason_code,
            ])->save();
        }

        return response()->json(array_merge($result, ['channel' => 'nfc']));
    }
}
