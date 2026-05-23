<?php

declare(strict_types=1);

use App\Services\Payments\Sandbox\ContractValidator;

it('passes a contract-clean Stripe charge response', function () {
    $body = [
        'simulated' => true,
        'gateway' => 'stripe',
        'state' => 'captured',
        'reason_code' => null,
        'redirect_url' => null,
        'instructions' => null,
        'webhook_events' => ['payment.authorized', 'payment.captured'],
    ];

    expect((new ContractValidator)->validate('stripe', 'charge', $body))->toBe([]);
});

it('flags missing required fields', function () {
    $body = ['gateway' => 'stripe'];

    $violations = (new ContractValidator)->validate('stripe', 'charge', $body);

    expect($violations)
        ->toContain('missing required field: simulated')
        ->toContain('missing required field: state');
});

it('flags type mismatches on required fields', function () {
    $body = ['simulated' => 'yes', 'gateway' => 'stripe', 'state' => 'captured'];

    $violations = (new ContractValidator)->validate('stripe', 'charge', $body);

    expect($violations[0])->toContain('expected type bool, got string');
});

it('flags type mismatches on optional fields when present', function () {
    $body = [
        'simulated' => true,
        'gateway' => 'paynow',
        'state' => 'auth_pending',
        'redirect_url' => 12345,           // wrong type
    ];

    $violations = (new ContractValidator)->validate('paynow', 'charge', $body);

    expect(implode("\n", $violations))->toContain('optional field redirect_url present but wrong type');
});

it('reports no-contract for unknown provider/operation pairs', function () {
    expect((new ContractValidator)->validate('mystery', 'charge', []))
        ->toContain('no contract defined for mystery/charge');
});
