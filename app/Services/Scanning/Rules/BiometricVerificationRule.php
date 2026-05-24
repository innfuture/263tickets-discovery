<?php

declare(strict_types=1);

namespace App\Services\Scanning\Rules;

use App\Services\Scanning\Contracts\BiometricProvider;
use App\Services\Scanning\Contracts\FraudRule;
use App\Services\Scanning\Data\BiometricResult;
use App\Services\Scanning\Data\FraudVerdict;
use App\Services\Scanning\Data\ScanContext;

/**
 * Re-verifies the ticket holder against an on-device biometric match
 * for events that require it. Activation:
 *
 *   - Per-event: events.metadata.biometric_threshold (float in [0,1])
 *     OR the platform-wide default `config('scanning.biometric_default_threshold')`.
 *   - Per-event: events.metadata.biometric_required (bool).
 *
 * Behaviour:
 *   - Event doesn't require biometric → ALLOW.
 *   - Required + missing attestation → DENY (`biometric_required`).
 *   - Provider returns pass → ALLOW.
 *   - Provider returns soft-fail → WARN.
 *   - Provider returns hard-fail → DENY.
 *
 * The actual match is delegated to a BiometricProvider implementation
 * — swap providers via config, no rule edits.
 */
class BiometricVerificationRule implements FraudRule
{
    public function __construct(protected BiometricProvider $provider) {}

    public function id(): string
    {
        return 'biometric_verification';
    }

    public function evaluate(ScanContext $context): FraudVerdict
    {
        $event = $context->event;
        if ($event === null) {
            return FraudVerdict::allow($this->id());
        }

        if (! (bool) ($event->biometric_required ?? false)) {
            return FraudVerdict::allow($this->id());
        }

        $threshold = (float) ($event->biometric_threshold
            ?? config('scanning.biometric_default_threshold', 0.85));

        if ($context->biometric === null) {
            return FraudVerdict::deny(
                'biometric_required',
                'This event requires biometric verification; no attestation supplied.',
                severity: 8,
                rule: $this->id(),
            );
        }

        $result = $this->provider->verify($context->biometric, $threshold);

        return match ($result->state) {
            BiometricResult::STATE_PASS => FraudVerdict::allow($this->id()),
            BiometricResult::STATE_SOFT_FAIL => FraudVerdict::warn(
                'biometric_soft_fail',
                (string) $result->message,
                severity: 3,
                rule: $this->id(),
            ),
            default => FraudVerdict::deny(
                'biometric_failed',
                (string) ($result->message ?? 'Biometric verification failed.'),
                severity: 8,
                rule: $this->id(),
            ),
        };
    }
}
