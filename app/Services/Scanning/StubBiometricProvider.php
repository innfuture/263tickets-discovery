<?php

declare(strict_types=1);

namespace App\Services\Scanning;

use App\Services\Scanning\Contracts\BiometricProvider;
use App\Services\Scanning\Data\BiometricAttestation;
use App\Services\Scanning\Data\BiometricResult;

/**
 * Dev / sandbox biometric provider.
 *
 *   - HARD_FAIL when the attestation is missing or malformed (caller
 *     forgot to send one).
 *   - HARD_FAIL on signature verification failure if a shared secret
 *     is set in `scanning.biometric_signing_secret` (so integration
 *     tests can exercise the bad-signature path).
 *   - PASS when confidence ≥ required.
 *   - SOFT_FAIL when confidence is in [required - 0.1, required) —
 *     close enough that the gate operator can decide.
 *   - HARD_FAIL otherwise.
 *
 * Real providers (AWS Rekognition, on-device Vision frameworks via
 * an attestation-relay backend, vendor SDK) implement the contract
 * the same way; only the verification call site changes.
 */
class StubBiometricProvider implements BiometricProvider
{
    public function verify(BiometricAttestation $attestation, float $requiredConfidence): BiometricResult
    {
        if ($attestation->confidence < 0 || $attestation->confidence > 1) {
            return BiometricResult::hardFail('Confidence outside [0, 1].');
        }

        $secret = (string) config('scanning.biometric_signing_secret', '');
        if ($secret !== '' && ! $this->signatureValid($attestation, $secret)) {
            return BiometricResult::hardFail('Bad device signature.');
        }

        if ($attestation->confidence >= $requiredConfidence) {
            return BiometricResult::pass($attestation->confidence);
        }

        if ($attestation->confidence >= $requiredConfidence - 0.1) {
            return BiometricResult::softFail(
                $attestation->confidence,
                sprintf('Confidence %.2f below threshold %.2f (within soft margin).',
                    $attestation->confidence, $requiredConfidence),
            );
        }

        return BiometricResult::hardFail(
            sprintf('Confidence %.2f below threshold %.2f.',
                $attestation->confidence, $requiredConfidence),
            $attestation->confidence,
        );
    }

    /**
     * HMAC-SHA256 over `algorithm|confidence|nonce|ticket_uuid`. Same
     * scheme on the device. Replay-safe via `nonce` — production
     * providers should track + reject seen nonces.
     */
    protected function signatureValid(BiometricAttestation $a, string $secret): bool
    {
        $msg = $a->algorithm.'|'.$a->confidence.'|'.$a->nonce.'|'.($a->ticketUuid ?? '');
        $expected = hash_hmac('sha256', $msg, $secret);

        return hash_equals($expected, $a->signature);
    }
}
