<?php

declare(strict_types=1);

use App\Enums\SandboxState;
use App\Services\Payments\Sandbox\Exceptions\IllegalTransitionException;
use App\Services\Payments\Sandbox\SandboxClock;
use App\Services\Payments\Sandbox\StateMachine;
use Tests\TestCase;

uses(TestCase::class);

it('permits documented forward transitions', function () {
    $machine = new StateMachine(app(SandboxClock::class));

    expect($machine->canTransition(SandboxState::INITIATED, SandboxState::AUTHORIZED))->toBeTrue()
        ->and($machine->canTransition(SandboxState::AUTHORIZED, SandboxState::CAPTURED))->toBeTrue()
        ->and($machine->canTransition(SandboxState::CAPTURED, SandboxState::REFUNDED))->toBeTrue()
        ->and($machine->canTransition(SandboxState::AUTHORIZED, SandboxState::VOIDED))->toBeTrue();
});

it('rejects backwards or illegal transitions', function () {
    $machine = new StateMachine(app(SandboxClock::class));

    expect($machine->canTransition(SandboxState::CAPTURED, SandboxState::AUTHORIZED))->toBeFalse()
        ->and($machine->canTransition(SandboxState::VOIDED, SandboxState::CAPTURED))->toBeFalse()
        ->and($machine->canTransition(SandboxState::FAILED, SandboxState::CAPTURED))->toBeFalse()
        ->and($machine->canTransition(SandboxState::REFUNDED, SandboxState::CAPTURED))->toBeFalse();
});

it('treats terminal states as terminal', function () {
    foreach ([
        SandboxState::VOIDED,
        SandboxState::REFUNDED,
        SandboxState::FAILED,
        SandboxState::DISPUTE_WON,
        SandboxState::DISPUTE_LOST,
    ] as $terminal) {
        expect($terminal->isTerminal())->toBeTrue();
    }

    foreach ([SandboxState::INITIATED, SandboxState::AUTHORIZED, SandboxState::CAPTURED] as $live) {
        expect($live->isTerminal())->toBeFalse();
    }
});

it('maps each state to a normalised PaymentStatus', function () {
    expect(SandboxState::CAPTURED->toPaymentStatus()->value)->toBe('captured')
        ->and(SandboxState::VOIDED->toPaymentStatus()->value)->toBe('cancelled')
        ->and(SandboxState::PART_REFUNDED->toPaymentStatus()->value)->toBe('partially_refunded')
        ->and(SandboxState::DISPUTE_WON->toPaymentStatus()->value)->toBe('captured');
});

it('IllegalTransitionException carries both states for debugging', function () {
    $e = new IllegalTransitionException(SandboxState::FAILED, SandboxState::CAPTURED, 'no');

    expect($e->from)->toBe(SandboxState::FAILED)
        ->and($e->to)->toBe(SandboxState::CAPTURED)
        ->and($e->getMessage())->toContain('failed → captured');
});
