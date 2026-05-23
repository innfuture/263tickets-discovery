<?php

declare(strict_types=1);

use App\Enums\SandboxScenario;
use App\Enums\SandboxState;
use App\Services\Payments\Data\ChargeRequest;
use App\Services\Payments\Data\Customer;
use App\Services\Payments\Data\Money;
use App\Services\Payments\Sandbox\MagicValues;
use App\Services\Payments\Sandbox\ScenarioResolver;
use Tests\TestCase;

uses(TestCase::class);

function makeChargeRequest(array $metadata = [], ?string $msisdn = null): ChargeRequest
{
    return new ChargeRequest(
        amount: Money::ofMajor('19.99', 'USD'),
        customer: new Customer(name: 'Test', email: 't@b.test', msisdn: $msisdn),
        reference: 'ref-'.bin2hex(random_bytes(4)),
        description: 'Test',
        metadata: $metadata,
    );
}

it('honors an explicit scenario in metadata over magic-value detection', function () {
    $resolver = new ScenarioResolver(new MagicValues);

    $request = makeChargeRequest([
        'scenario' => SandboxScenario::DECLINE_DO_NOT_HONOR->value,
        // Magic value that would otherwise resolve to SUCCESS.
        'pan' => '4242424242424242',
    ]);

    expect($resolver->resolveScenario($request))->toBe(SandboxScenario::DECLINE_DO_NOT_HONOR);
});

it('auto-detects scenario from a magic card PAN', function () {
    $resolver = new ScenarioResolver(new MagicValues);

    $request = makeChargeRequest(['pan' => '4000000000009995']);

    expect($resolver->resolveScenario($request))->toBe(SandboxScenario::DECLINE_INSUFFICIENT);
});

it('auto-detects scenario from an MSISDN suffix for mobile method', function () {
    $resolver = new ScenarioResolver(new MagicValues);

    $request = makeChargeRequest(['method' => 'mobile'], msisdn: '263772229999');

    expect($resolver->resolveScenario($request))->toBe(SandboxScenario::MOBILE_USER_CANCEL);
});

it('falls back to SUCCESS when nothing matches', function () {
    $resolver = new ScenarioResolver(new MagicValues);

    expect($resolver->resolveScenario(makeChargeRequest()))->toBe(SandboxScenario::SUCCESS);
});

it('produces an Outcome whose immediate state is FAILED for declines', function () {
    $resolver = new ScenarioResolver(new MagicValues);

    $outcome = $resolver->outcomeFor(SandboxScenario::DECLINE_INSUFFICIENT, makeChargeRequest());

    expect($outcome->immediateState)->toBe(SandboxState::FAILED)
        ->and($outcome->reasonCode)->toBe('insufficient_funds')
        ->and($outcome->webhookEvents)->toContain('payment.failed');
});

it('produces a long ACH delay for return-code scenarios', function () {
    $resolver = new ScenarioResolver(new MagicValues);

    $outcome = $resolver->outcomeFor(SandboxScenario::ACH_R01_NSF, makeChargeRequest());

    expect($outcome->immediateState)->toBe(SandboxState::AUTH_PENDING)
        ->and($outcome->followUpState)->toBe(SandboxState::FAILED)
        ->and($outcome->webhookDelaySeconds)->toBe(172_800)
        ->and($outcome->reasonCode)->toBe('R01');
});

it('schedules duplicate deliveries for webhook.duplicate scenario', function () {
    $resolver = new ScenarioResolver(new MagicValues);

    $outcome = $resolver->outcomeFor(SandboxScenario::WEBHOOK_DUPLICATE, makeChargeRequest());

    expect($outcome->duplicateCount)->toBe(3)
        ->and($outcome->webhookEvents)->toHaveCount(2);
});

it('produces an out-of-order outcome when asked', function () {
    $resolver = new ScenarioResolver(new MagicValues);

    $outcome = $resolver->outcomeFor(SandboxScenario::WEBHOOK_OUT_OF_ORDER, makeChargeRequest());

    expect($outcome->outOfOrder)->toBeTrue();
});

it('produces a 3DS challenge URL pointing at the mock ACS', function () {
    $resolver = new ScenarioResolver(new MagicValues);

    $outcome = $resolver->outcomeFor(
        SandboxScenario::THREE_DS_CHALLENGE_PASSED,
        makeChargeRequest(),
    );

    expect($outcome->redirectUrl)
        ->toContain('/sandbox/payments/three-ds/acs')
        ->and($outcome->redirectUrl)->toContain('accept=1');
});
