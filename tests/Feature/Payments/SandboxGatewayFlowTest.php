<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Models\SandboxTransaction;
use App\Models\SandboxWebhookOutbox;
use App\Services\Payments\Data\ChargeRequest;
use App\Services\Payments\Data\Customer;
use App\Services\Payments\Data\Money;
use App\Services\Payments\Data\RefundRequest;
use App\Services\Payments\PaymentManager;
use Tests\Support\UsesSandboxPayments;

uses(UsesSandboxPayments::class);

beforeEach(function () {
    $this->setUpUsesSandboxPayments();
});

it('routes through SandboxGateway and creates an authorized + captured transaction for the success scenario', function () {
    /** @var PaymentManager $payments */
    $payments = app(PaymentManager::class);

    $result = $payments->gateway('sandbox')->charge(new ChargeRequest(
        amount: Money::ofMajor('19.99', 'USD'),
        customer: new Customer(name: 'Test', email: 't@t.test'),
        reference: 'ord_'.bin2hex(random_bytes(4)),
        description: 'Test',
        metadata: ['scenario' => 'success', 'emulate' => 'stripe'],
    ));

    expect($result->status)->toBe(PaymentStatus::AUTHORIZED)
        ->and($result->raw['simulated'])->toBeTrue();

    expect(SandboxTransaction::where('reference', $result->reference)->first()->state)
        ->toBe('authorized');
});

it('lands directly on FAILED with the right reason code for an insufficient_funds decline', function () {
    $result = app(PaymentManager::class)->gateway('sandbox')->charge(new ChargeRequest(
        amount: Money::ofMajor('19.99', 'USD'),
        customer: new Customer(name: 'X', email: 'x@x.test'),
        reference: 'ord_decline_'.bin2hex(random_bytes(4)),
        description: 'Decline',
        metadata: ['pan' => '4000000000009995', 'emulate' => 'stripe'],
    ));

    expect($result->status)->toBe(PaymentStatus::FAILED);

    $row = SandboxTransaction::where('reference', $result->reference)->first();
    expect($row->state)->toBe('failed')
        ->and($row->reason_code)->toBe('insufficient_funds');
});

it('queues the scenario-specified webhook events on the outbox', function () {
    app(PaymentManager::class)->gateway('sandbox')->charge(new ChargeRequest(
        amount: Money::ofMajor('5.00', 'USD'),
        customer: new Customer,
        reference: 'ord_wh_'.bin2hex(random_bytes(4)),
        description: 'Webhook test',
        metadata: ['scenario' => 'success'],
    ));

    $types = SandboxWebhookOutbox::query()
        ->where('sandbox_merchant_id', $this->sandboxMerchant->id)
        ->pluck('type')
        ->all();

    expect($types)->toContain('payment.authorized')
        ->and($types)->toContain('payment.captured');
});

it('returns the same response for a retried call with the same Idempotency-Key', function () {
    $key = 'sbx_idem_'.bin2hex(random_bytes(8));
    $req = new ChargeRequest(
        amount: Money::ofMajor('7.00', 'USD'),
        customer: new Customer,
        reference: 'ord_idem_'.bin2hex(random_bytes(4)),
        description: 'idem',
        metadata: ['idempotency_key' => $key, 'scenario' => 'success'],
    );

    $a = app(PaymentManager::class)->gateway('sandbox')->charge($req);
    $b = app(PaymentManager::class)->gateway('sandbox')->charge($req);

    expect($b->gatewayReference)->toBe($a->gatewayReference);
    expect(SandboxTransaction::count())->toBe(1);
});

it('moves a captured transaction to REFUNDED via the kernel refund path', function () {
    $manager = app(PaymentManager::class);

    $charge = $manager->gateway('sandbox')->charge(new ChargeRequest(
        amount: Money::ofMajor('10.00', 'USD'),
        customer: new Customer,
        reference: 'ord_refund_'.bin2hex(random_bytes(4)),
        description: 'Refund test',
        metadata: ['scenario' => 'success'],
    ));

    $manager->gateway('sandbox')->refund(new RefundRequest(
        reference: $charge->reference,
        gatewayReference: $charge->gatewayReference,
        amount: null,
        reason: 'customer_request',
    ));

    $this->assertSandboxState($charge->reference, 'refunded');
});

it('flushDue delivers queued webhooks synchronously when called by the test helper', function () {
    app(PaymentManager::class)->gateway('sandbox')->charge(new ChargeRequest(
        amount: Money::ofMajor('1.00', 'USD'),
        customer: new Customer,
        reference: 'ord_flush_'.bin2hex(random_bytes(4)),
        description: 'Flush',
        metadata: ['scenario' => 'success'],
    ));

    $queuedBefore = SandboxWebhookOutbox::where('status', 'queued')->count();
    expect($queuedBefore)->toBeGreaterThan(0);

    $this->processSandboxWebhooks();

    $queuedAfter = SandboxWebhookOutbox::where('status', 'queued')->count();
    expect($queuedAfter)->toBe(0);
});

it('advances the virtual clock and surfaces follow-up state through the webhook dispatcher', function () {
    $charge = app(PaymentManager::class)->gateway('sandbox')->charge(new ChargeRequest(
        amount: Money::ofMajor('2.00', 'USD'),
        customer: new Customer,
        reference: 'ord_clock_'.bin2hex(random_bytes(4)),
        description: 'Clock',
        // ACH return scenarios require a virtual T+2 delay before resolving.
        metadata: ['method' => 'ach', 'account_number' => '000111111111'],
    ));

    $this->assertSandboxState($charge->reference, 'auth_pending');

    // Skip past T+2.
    $this->advanceSandboxClock(seconds: 3 * 86400);

    $this->assertSandboxState($charge->reference, 'failed');
});
