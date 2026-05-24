<?php

declare(strict_types=1);

use App\Services\Scanning\Data\FraudVerdict;

it('builds an allow verdict with no message or reason', function () {
    $v = FraudVerdict::allow('rule_a');

    expect($v->outcome)->toBe('allow')
        ->and($v->severity)->toBe(0)
        ->and($v->reasonCode)->toBeNull()
        ->and($v->rule)->toBe('rule_a');
});

it('floors warn severity at 1 and deny at 5', function () {
    expect(FraudVerdict::warn('x', 'msg', severity: 0)->severity)->toBe(1)
        ->and(FraudVerdict::deny('x', 'msg', severity: 1)->severity)->toBe(5);
});

it('isWarn and isDeny report correctly', function () {
    expect(FraudVerdict::warn('x', 'msg')->isWarn())->toBeTrue()
        ->and(FraudVerdict::warn('x', 'msg')->isDeny())->toBeFalse()
        ->and(FraudVerdict::deny('x', 'msg')->isDeny())->toBeTrue();
});

it('serialises to array with all fields', function () {
    $a = FraudVerdict::warn('rate_high', 'too fast', severity: 3, rule: 'velocity')->toArray();

    expect($a)->toBe([
        'rule' => 'velocity',
        'outcome' => 'warn',
        'severity' => 3,
        'reason_code' => 'rate_high',
        'message' => 'too fast',
    ]);
});
