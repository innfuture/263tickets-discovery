<?php

declare(strict_types=1);

use App\Models\ScannerDevice;
use App\Models\ScannerProfile;
use App\Services\Scanning\Contracts\FraudRule;
use App\Services\Scanning\Data\FraudVerdict;
use App\Services\Scanning\Data\ScanContext;
use App\Services\Scanning\FraudEngine;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Inline fake rules so the pipeline test doesn't depend on real rule
 * implementations. The engine resolves rule classes from
 * config('scanning.fraud_rules') — set that to our fakes in each test.
 */
class FakeAllow implements FraudRule
{
    public function id(): string
    {
        return 'fake_allow';
    }

    public function evaluate(ScanContext $context): FraudVerdict
    {
        return FraudVerdict::allow($this->id());
    }
}

class FakeWarn implements FraudRule
{
    public function id(): string
    {
        return 'fake_warn';
    }

    public function evaluate(ScanContext $context): FraudVerdict
    {
        return FraudVerdict::warn('soft', 'soft message', severity: 2, rule: $this->id());
    }
}

class FakeStrongDeny implements FraudRule
{
    public function id(): string
    {
        return 'fake_deny';
    }

    public function evaluate(ScanContext $context): FraudVerdict
    {
        return FraudVerdict::deny('blocked', 'denied!', severity: 9, rule: $this->id());
    }
}

function fakeContext(): ScanContext
{
    return new ScanContext(
        device: new ScannerDevice(['device_label' => 'x', 'status' => 'active']),
        profile: new ScannerProfile([
            'organisation_id' => 'org',
            'name' => 'p',
            'capabilities' => ['scan'],
            'max_scans_per_minute' => 60,
            'duplicate_window_seconds' => 10,
            'status' => 'active',
        ]),
        payload: 'X',
        ticket: null,
        event: null,
        clientLat: null,
        clientLng: null,
        clientIp: null,
        serverAt: CarbonImmutable::now(),
        clientAt: null,
    );
}

it('returns allow with empty flags when no rule objects', function () {
    config()->set('scanning.fraud_rules', [FakeAllow::class]);

    $result = app(FraudEngine::class)->evaluate(fakeContext());

    expect($result['verdict']->outcome)->toBe('allow')
        ->and($result['flags'])->toBe([]);
});

it('picks deny over warn when both rules speak', function () {
    config()->set('scanning.fraud_rules', [FakeWarn::class, FakeStrongDeny::class]);

    $result = app(FraudEngine::class)->evaluate(fakeContext());

    expect($result['verdict']->outcome)->toBe('deny')
        ->and($result['verdict']->reasonCode)->toBe('blocked')
        ->and($result['flags'])->toHaveCount(2);
});

it('skips classes that do not exist', function () {
    config()->set('scanning.fraud_rules', [FakeWarn::class, '\\Does\\Not\\Exist']);

    $result = app(FraudEngine::class)->evaluate(fakeContext());

    expect($result['verdict']->outcome)->toBe('warn')
        ->and($result['flags'])->toHaveCount(1);
});

it('preserves every non-allow rule verdict in the flags list', function () {
    config()->set('scanning.fraud_rules', [FakeAllow::class, FakeWarn::class, FakeAllow::class, FakeStrongDeny::class]);

    $result = app(FraudEngine::class)->evaluate(fakeContext());

    expect($result['flags'])->toHaveCount(2)
        ->and(collect($result['flags'])->pluck('rule')->all())->toContain('fake_warn')
        ->and(collect($result['flags'])->pluck('rule')->all())->toContain('fake_deny');
});
