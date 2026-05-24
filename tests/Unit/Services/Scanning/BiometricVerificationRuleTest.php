<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\OfflineTicket;
use App\Models\ScannerDevice;
use App\Models\ScannerProfile;
use App\Services\Scanning\Contracts\BiometricProvider;
use App\Services\Scanning\Data\BiometricAttestation;
use App\Services\Scanning\Data\BiometricResult;
use App\Services\Scanning\Data\ScanContext;
use App\Services\Scanning\Rules\BiometricVerificationRule;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

class FakeBiometricProvider implements BiometricProvider
{
    public function __construct(public BiometricResult $result) {}

    public function verify(BiometricAttestation $attestation, float $requiredConfidence): BiometricResult
    {
        return $this->result;
    }
}

function bioContext(?Event $event, ?BiometricAttestation $biometric = null): ScanContext
{
    $profile = new ScannerProfile([
        'organisation_id' => 'org',
        'name' => 'P',
        'capabilities' => ['scan'],
        'max_scans_per_minute' => 60,
        'duplicate_window_seconds' => 10,
        'status' => 'active',
    ]);
    $profile->id = 1;
    $device = new ScannerDevice(['device_label' => 'd', 'status' => 'active']);
    $device->id = 1;
    $device->setRelation('profile', $profile);

    return new ScanContext(
        device: $device,
        profile: $profile,
        payload: 'X',
        ticket: new OfflineTicket(['is_voided' => false]),
        event: $event,
        clientLat: null,
        clientLng: null,
        clientIp: null,
        serverAt: CarbonImmutable::now(),
        clientAt: null,
        biometric: $biometric,
    );
}

it('allows when the event has no biometric_required column set', function () {
    $event = new Event;
    $event->biometric_required = false;
    $rule = new BiometricVerificationRule(new FakeBiometricProvider(BiometricResult::pass(1.0)));

    expect($rule->evaluate(bioContext($event))->outcome)->toBe('allow');
});

it('denies with biometric_required code when required but no attestation supplied', function () {
    $event = new Event;
    $event->biometric_required = true;
    $rule = new BiometricVerificationRule(new FakeBiometricProvider(BiometricResult::pass(1.0)));

    $v = $rule->evaluate(bioContext($event, biometric: null));

    expect($v->outcome)->toBe('deny')
        ->and($v->reasonCode)->toBe('biometric_required');
});

it('allows when the provider returns pass', function () {
    $event = new Event;
    $event->biometric_required = true;
    $att = new BiometricAttestation('face_match.v1', 0.95, 'n', 's', 'tkt');
    $rule = new BiometricVerificationRule(new FakeBiometricProvider(BiometricResult::pass(0.95)));

    expect($rule->evaluate(bioContext($event, $att))->outcome)->toBe('allow');
});

it('warns when the provider returns soft_fail', function () {
    $event = new Event;
    $event->biometric_required = true;
    $att = new BiometricAttestation('face_match.v1', 0.80, 'n', 's', 'tkt');
    $rule = new BiometricVerificationRule(new FakeBiometricProvider(
        BiometricResult::softFail(0.80, 'close call'),
    ));

    $v = $rule->evaluate(bioContext($event, $att));
    expect($v->outcome)->toBe('warn')
        ->and($v->reasonCode)->toBe('biometric_soft_fail');
});

it('denies when the provider returns hard_fail', function () {
    $event = new Event;
    $event->biometric_required = true;
    $att = new BiometricAttestation('face_match.v1', 0.40, 'n', 's', 'tkt');
    $rule = new BiometricVerificationRule(new FakeBiometricProvider(
        BiometricResult::hardFail('confidence too low', 0.40),
    ));

    $v = $rule->evaluate(bioContext($event, $att));
    expect($v->outcome)->toBe('deny')
        ->and($v->reasonCode)->toBe('biometric_failed');
});

it('allows when there is no resolved event', function () {
    $rule = new BiometricVerificationRule(new FakeBiometricProvider(BiometricResult::pass(1.0)));
    expect($rule->evaluate(bioContext(null))->outcome)->toBe('allow');
});
