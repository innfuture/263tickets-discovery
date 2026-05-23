<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Services\Payments\Data\ChargeRequest;
use App\Services\Payments\Data\Customer;
use App\Services\Payments\Data\Money;
use App\Services\Payments\Data\RefundRequest;
use App\Services\Payments\Drivers\SandboxGateway;
use App\Services\Payments\Exceptions\GatewayNotConfiguredException;
use App\Services\Payments\Exceptions\PaymentException;
use App\Services\Payments\Sandbox\SandboxKernel;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

function httpModeConfig(): array
{
    return [
        'driver' => SandboxGateway::class,
        'enabled' => true,
        'label' => 'Sandbox (test only)',
        'supported_currencies' => ['USD'],
        'http' => ['timeout' => 5, 'connect_timeout' => 5, 'retries' => 0],
    ];
}

beforeEach(function () {
    config()->set('payments.sandbox.kernel', 'http');
    config()->set('payments.sandbox.base_url', 'https://sandbox.example');
    config()->set('payments.sandbox.service_key', 'test-bearer');
    config()->set('payments.gateways.sandbox', httpModeConfig());
});

it('proxies charge() to the HTTP service endpoint with bearer auth', function () {
    Http::fake([
        'sandbox.example/sandbox/service/v1/charges' => Http::response([
            'uuid' => '01HXR000',
            'reference' => 'ord_1',
            'provider_reference' => 'pi_abc',
            'state' => 'authorized',
            'payment_status' => 'authorized',
            'scenario' => 'success',
            'reason_code' => null,
            'amount_minor' => 1999,
            'currency' => 'USD',
            'redirect_url' => null,
            'poll_url' => null,
            'instructions' => null,
            'response_body' => ['simulated' => true],
        ], 201),
    ]);

    /** @var SandboxGateway $driver */
    $driver = new SandboxGateway(httpModeConfig(), app(SandboxKernel::class));

    $result = $driver->charge(new ChargeRequest(
        amount: Money::ofMajor('19.99', 'USD'),
        customer: new Customer(email: 't@t.test'),
        reference: 'ord_1',
        description: 'Test',
        metadata: ['sandbox_merchant_slug' => 'sandbox-default'],
    ));

    expect($result->status)->toBe(PaymentStatus::AUTHORIZED)
        ->and($result->gatewayReference)->toBe('pi_abc');

    Http::assertSent(function ($req) {
        return $req->url() === 'https://sandbox.example/sandbox/service/v1/charges'
            && $req->hasHeader('Authorization', 'Bearer test-bearer')
            && $req['reference'] === 'ord_1';
    });
});

it('proxies refund() to the HTTP service endpoint', function () {
    Http::fake([
        'sandbox.example/sandbox/service/v1/refunds' => Http::response([
            'uuid' => 'refund-1',
            'transaction_uuid' => '01HXR000',
            'amount_minor' => 1999,
            'currency' => 'USD',
            'state' => 'succeeded',
            'response_body' => ['simulated' => true],
        ], 201),
    ]);

    $driver = new SandboxGateway(httpModeConfig(), app(SandboxKernel::class));

    $result = $driver->refund(new RefundRequest(
        reference: 'ord_1',
        gatewayReference: 'pi_abc',
        amount: null,
        reason: 'customer_request',
    ));

    expect($result->status)->toBe(PaymentStatus::REFUNDED)
        ->and($result->gatewayReference)->toBe('refund-1');
});

it('proxies status() to the HTTP service endpoint', function () {
    Http::fake([
        'sandbox.example/sandbox/service/v1/transactions*' => Http::response([
            'reference' => 'ord_1',
            'provider_reference' => 'pi_abc',
            'payment_status' => 'captured',
            'state' => 'captured',
            'message' => null,
            'response_body' => [],
        ], 200),
    ]);

    $driver = new SandboxGateway(httpModeConfig(), app(SandboxKernel::class));

    $result = $driver->status('ord_1', 'pi_abc');

    expect($result->status)->toBe(PaymentStatus::CAPTURED)
        ->and($result->reference)->toBe('ord_1');
});

it('throws GatewayNotConfiguredException when base_url or service_key is missing in HTTP mode', function () {
    config()->set('payments.sandbox.service_key', '');

    $driver = new SandboxGateway(httpModeConfig(), app(SandboxKernel::class));

    expect(fn () => $driver->charge(new ChargeRequest(
        amount: Money::ofMajor('1.00', 'USD'),
        customer: new Customer,
        reference: 'r1',
        description: 'd',
    )))->toThrow(GatewayNotConfiguredException::class);
});

it('surfaces remote 5xx as PaymentException with body included', function () {
    Http::fake([
        'sandbox.example/sandbox/service/v1/charges' => Http::response('upstream broken', 502),
    ]);

    $driver = new SandboxGateway(httpModeConfig(), app(SandboxKernel::class));

    expect(fn () => $driver->charge(new ChargeRequest(
        amount: Money::ofMajor('1.00', 'USD'),
        customer: new Customer,
        reference: 'r1',
        description: 'd',
    )))->toThrow(PaymentException::class);
});
