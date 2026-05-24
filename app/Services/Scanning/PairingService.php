<?php

declare(strict_types=1);

namespace App\Services\Scanning;

use App\Models\ScannerDevice;
use App\Models\ScannerPairingCode;
use App\Models\ScannerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pairing flow:
 *
 *   1. Operator opens the Scanners admin → "Generate pairing code"
 *      for a profile → `issueCode()`.
 *   2. Mobile app types the 8-char code into its setup screen →
 *      `redeem($code, $deviceMeta)` returns the plaintext token (only
 *      ever shown once).
 *   3. Mobile app stores the token in its OS keychain and sends it as
 *      `Authorization: Bearer …` on every subsequent request.
 *
 * Tokens are stored sha256 — neither support nor an attacker with DB
 * access can recover a working token.
 */
class PairingService
{
    public function issueCode(ScannerProfile $profile, ?string $hintLabel, ?User $creator): ScannerPairingCode
    {
        // Generate-and-retry to handle the (vanishingly unlikely) case
        // of a collision with an unexpired code.
        $code = $this->uniqueCode();

        return ScannerPairingCode::create([
            'scanner_profile_id' => $profile->id,
            'code' => $code,
            'hint_label' => $hintLabel,
            'expires_at' => now()->addMinutes((int) config('scanning.pairing.code_ttl_minutes', 30)),
            'created_by' => $creator?->id,
        ]);
    }

    /**
     * Exchange a pairing code for a fresh device + plaintext token.
     *
     * @param  array{device_label: string, platform?: string, app_version?: ?string, hardware_id?: ?string}  $deviceMeta
     * @return array{device: ScannerDevice, token: string}
     */
    public function redeem(string $codeInput, array $deviceMeta): array
    {
        $code = strtoupper(trim($codeInput));

        return DB::transaction(function () use ($code, $deviceMeta) {
            $row = ScannerPairingCode::query()
                ->where('code', $code)
                ->lockForUpdate()
                ->first();

            if ($row === null || ! $row->isUsable()) {
                throw new RuntimeException('Pairing code is invalid or expired.');
            }

            $token = $this->generateToken();

            $device = ScannerDevice::create([
                'scanner_profile_id' => $row->scanner_profile_id,
                'token_hash' => hash('sha256', $token),
                'token_prefix' => substr($token, 0, 12),
                'device_label' => substr($deviceMeta['device_label'], 0, 120),
                'platform' => $deviceMeta['platform'] ?? 'other',
                'app_version' => $deviceMeta['app_version'] ?? null,
                'hardware_id' => $deviceMeta['hardware_id'] ?? null,
                'last_seen_at' => now(),
                'status' => 'active',
            ]);

            $row->update([
                'used_at' => now(),
                'used_by_device_id' => $device->id,
            ]);

            return ['device' => $device, 'token' => $token];
        });
    }

    public function revokeDevice(ScannerDevice $device, ?string $reason = null): void
    {
        $device->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoked_reason' => $reason ? substr($reason, 0, 255) : 'Revoked by organizer.',
        ]);
    }

    protected function uniqueCode(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $code = ScannerPairingCode::generateCode();
            if (! ScannerPairingCode::query()->where('code', $code)->exists()) {
                return $code;
            }
        }
        throw new RuntimeException('Could not generate a unique pairing code after 10 attempts.');
    }

    protected function generateToken(): string
    {
        return (string) config('scanning.auth.token_display_prefix', 'scn_').bin2hex(random_bytes(24));
    }
}
