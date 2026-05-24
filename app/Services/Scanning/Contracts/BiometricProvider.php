<?php

declare(strict_types=1);

namespace App\Services\Scanning\Contracts;

use App\Services\Scanning\Data\BiometricAttestation;
use App\Services\Scanning\Data\BiometricResult;

/**
 * Verifies a biometric attestation produced on the scanning device.
 *
 * Privacy-by-design contract: the *raw biometric* (face vector, etc.)
 * NEVER leaves the device. The scanner does the match locally and
 * sends only:
 *
 *   - a confidence score in [0, 1]
 *   - the algorithm + version used
 *   - a signed attestation blob the provider can verify
 *
 * The server-side provider verifies the signature (replay-protected
 * by `nonce`) and applies the policy from EventBiometricSettings to
 * decide pass / fail / soft-fail.
 *
 * Default implementation is StubBiometricProvider — accepts any
 * well-formed attestation. Production swap is one config key:
 *
 *   SCANNING_BIOMETRIC_PROVIDER=\Vendor\FaceProvider::class
 */
interface BiometricProvider
{
    public function verify(BiometricAttestation $attestation, float $requiredConfidence): BiometricResult;
}
