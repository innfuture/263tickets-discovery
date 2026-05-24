<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Distribution;

use App\Http\Controllers\Controller;
use App\Models\DistributorDevice;
use App\Models\DistributorPairingCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mirror of the scanner-device pairing flow. Distributor admin
 * generates a short-lived code in the dashboard → POS terminal calls
 * /pair with the code + an attestation public key → exchanges for a
 * long-lived bearer token + persists the device row.
 *
 * Bearer token is shown once at /pair; never recoverable.
 */
class DevicePairingController extends Controller
{
    public function pair(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'min:8', 'max:120'],
            'label' => ['required', 'string', 'max:191'],
            'platform' => ['nullable', 'string', 'max:32'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'attestation_pubkey' => ['required', 'string', 'min:64'],
        ]);

        $codeHash = hash('sha256', (string) $validated['code']);

        $pairing = DistributorPairingCode::query()
            ->where('code_hash', $codeHash)
            ->whereNull('redeemed_at')
            ->where('expires_at', '>', now())
            ->lockForUpdate()
            ->first();

        if (! $pairing) {
            return response()->json(['error' => 'invalid_or_expired_code'], 401);
        }

        return DB::transaction(function () use ($pairing, $validated): JsonResponse {
            $plaintext = 'dpt_'.Str::random(48);
            $tokenHash = hash('sha256', $plaintext);

            $device = DistributorDevice::create([
                'distributor_id' => $pairing->distributor_id,
                'label' => $validated['label'],
                'pairing_token_hash' => $tokenHash,
                'paired_at' => now(),
                'attestation_pubkey' => $validated['attestation_pubkey'],
                'platform' => $validated['platform'] ?? null,
                'app_version' => $validated['app_version'] ?? null,
                'last_seen_at' => now(),
                'status' => DistributorDevice::STATUS_ACTIVE,
                'capabilities' => ['sell', 'transfer_request', 'void_request'],
            ]);

            $pairing->forceFill([
                'redeemed_at' => Carbon::now(),
                'redeemed_by_device_id' => $device->id,
            ])->save();

            return response()->json([
                'data' => [
                    'device_uuid' => $device->uuid,
                    'bearer_token' => $plaintext,
                    'distributor_uuid' => $pairing->distributor->uuid,
                    'note' => 'Token shown once — store it securely on the device.',
                ],
            ], 201);
        });
    }
}
