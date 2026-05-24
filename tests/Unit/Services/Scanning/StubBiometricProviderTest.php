<?php

declare(strict_types=1);

use App\Services\Scanning\Data\BiometricAttestation;
use App\Services\Scanning\Data\BiometricResult;
use App\Services\Scanning\StubBiometricProvider;
use Tests\TestCase;

uses(TestCase::class);

function attestation(float $confidence, ?string $signature = null, string $algo = 'face_match.v1'): BiometricAttestation
{
    return new BiometricAttestation(
        algorithm: $algo,
        confidence: $confidence,
        nonce: 'nonce-abc',
        signature: $signature ?? 'unsigned',
        ticketUuid: 'tkt-1',
    );
}

it('passes when confidence is at or above threshold', function () {
    config()->set('scanning.biometric_signing_secret', null);
    $r = (new StubBiometricProvider)->verify(attestation(0.9), 0.85);
    expect($r->state)->toBe(BiometricResult::STATE_PASS)
        ->and($r->confidence)->toBe(0.9);
});

it('soft-fails when confidence is just below threshold (within 0.1)', function () {
    config()->set('scanning.biometric_signing_secret', null);
    $r = (new StubBiometricProvider)->verify(attestation(0.80), 0.85);
    expect($r->state)->toBe(BiometricResult::STATE_SOFT_FAIL)
        ->and($r->message)->toContain('below threshold');
});

it('hard-fails when confidence is far below threshold', function () {
    config()->set('scanning.biometric_signing_secret', null);
    $r = (new StubBiometricProvider)->verify(attestation(0.40), 0.85);
    expect($r->state)->toBe(BiometricResult::STATE_HARD_FAIL);
});

it('hard-fails on out-of-range confidence', function () {
    config()->set('scanning.biometric_signing_secret', null);
    $r = (new StubBiometricProvider)->verify(attestation(1.4), 0.85);
    expect($r->state)->toBe(BiometricResult::STATE_HARD_FAIL)
        ->and($r->message)->toContain('outside');
});

it('hard-fails on bad signature when a secret is configured', function () {
    config()->set('scanning.biometric_signing_secret', 'topsecret');
    // Wrong signature → reject.
    $r = (new StubBiometricProvider)->verify(attestation(0.95, 'wrong-sig'), 0.85);
    expect($r->state)->toBe(BiometricResult::STATE_HARD_FAIL)
        ->and($r->message)->toBe('Bad device signature.');
});

it('passes when signature matches the HMAC scheme', function () {
    $secret = 'topsecret';
    config()->set('scanning.biometric_signing_secret', $secret);

    $a = new BiometricAttestation(
        algorithm: 'face_match.v1',
        confidence: 0.95,
        nonce: 'fixed-nonce',
        signature: hash_hmac('sha256', 'face_match.v1|0.95|fixed-nonce|tkt-1', $secret),
        ticketUuid: 'tkt-1',
    );

    expect((new StubBiometricProvider)->verify($a, 0.85)->state)->toBe(BiometricResult::STATE_PASS);
});

it('BiometricAttestation::fromArray rejects missing required keys', function () {
    expect(BiometricAttestation::fromArray(['algorithm' => 'x', 'confidence' => 0.9, 'nonce' => 'n'], 'tkt'))
        ->toBeNull();
    expect(BiometricAttestation::fromArray(null, 'tkt'))->toBeNull();
});

it('BiometricAttestation::fromArray builds when all 4 keys present', function () {
    $a = BiometricAttestation::fromArray([
        'algorithm' => 'face_match.v1',
        'confidence' => 0.91,
        'nonce' => 'n',
        'signature' => 's',
    ], 'tkt-1');

    expect($a)->not->toBeNull()
        ->and($a->algorithm)->toBe('face_match.v1')
        ->and($a->confidence)->toBe(0.91)
        ->and($a->ticketUuid)->toBe('tkt-1');
});
