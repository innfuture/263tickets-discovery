<?php

declare(strict_types=1);

use App\Models\TicketCustodyLedgerEntry as Ledger;
use App\Services\Distribution\TicketLifecyclePolicy;

it('allows the canonical happy path', function () {
    $p = new TicketLifecyclePolicy();
    expect($p->canTransition(Ledger::EVENT_PRINTED, Ledger::EVENT_DISPATCHED))->toBeTrue()
        ->and($p->canTransition(Ledger::EVENT_DISPATCHED, Ledger::EVENT_RECEIVED))->toBeTrue()
        ->and($p->canTransition(Ledger::EVENT_RECEIVED, Ledger::EVENT_SOLD))->toBeTrue()
        ->and($p->canTransition(Ledger::EVENT_SOLD, Ledger::EVENT_ACTIVATED))->toBeTrue()
        ->and($p->canTransition(Ledger::EVENT_ACTIVATED, Ledger::EVENT_SCANNED))->toBeTrue();
});

it('blocks selling a dispatched ticket (not yet received)', function () {
    $p = new TicketLifecyclePolicy();
    expect($p->canTransition(Ledger::EVENT_DISPATCHED, Ledger::EVENT_SOLD))->toBeFalse();
});

it('blocks scanning a sold-but-not-activated ticket', function () {
    // Activation must happen between sold and scanned — it's a separate
    // ledger row so refunds can void activation without rewriting the sale.
    $p = new TicketLifecyclePolicy();
    expect($p->canTransition(Ledger::EVENT_SOLD, Ledger::EVENT_SCANNED))->toBeFalse();
});

it('blocks any transition out of a terminal state', function () {
    $p = new TicketLifecyclePolicy();
    foreach ([Ledger::EVENT_SCANNED, Ledger::EVENT_VOIDED, Ledger::EVENT_RECOVERED] as $terminal) {
        expect($p->canTransition($terminal, Ledger::EVENT_SOLD))->toBeFalse()
            ->and($p->canTransition($terminal, Ledger::EVENT_VOIDED))->toBeFalse()
            ->and($p->isTerminal($terminal))->toBeTrue();
    }
});

it('allows audit-only events from any non-terminal state', function () {
    $p = new TicketLifecyclePolicy();
    foreach ([Ledger::EVENT_RECEIVED, Ledger::EVENT_SOLD] as $state) {
        expect($p->canTransition($state, Ledger::EVENT_SPOT_AUDIT_OK))->toBeTrue()
            ->and($p->canTransition($state, Ledger::EVENT_GEO_ANOMALY))->toBeTrue();
    }
});

it('blocks audit-only events from a terminal state', function () {
    $p = new TicketLifecyclePolicy();
    expect($p->canTransition(Ledger::EVENT_VOIDED, Ledger::EVENT_SPOT_AUDIT_OK))->toBeFalse()
        ->and($p->canTransition(Ledger::EVENT_SCANNED, Ledger::EVENT_GEO_ANOMALY))->toBeFalse();
});

it('allows recovery from received state', function () {
    $p = new TicketLifecyclePolicy();
    expect($p->canTransition(Ledger::EVENT_RECEIVED, Ledger::EVENT_RECOVERED))->toBeTrue();
});
