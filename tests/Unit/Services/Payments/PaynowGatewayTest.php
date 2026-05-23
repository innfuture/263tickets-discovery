<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Services\Payments\Data\ChargeRequest;
use App\Services\Payments\Data\Customer;
use App\Services\Payments\Data\Money;
use App\Services\Payments\Drivers\PaynowGateway;
use App\Services\Payments\Exceptions\WebhookSignatureException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

function paynowConfig(): array
{
    return [
        'driver' => PaynowGateway::class,
        'enabled' => true,
        'label' => 'Paynow',
        'base_url' => 'https://paynow.test',
        'integration_id' => 'iid',
        'integration_key' => 'ikey',
        'express' => ['integration_id' => 'iid', 'integration_key' => 'ikey'],
        'supported_currencies' => ['USD'],
        'http' => ['timeout' => 5, 'connect_timeout' => 5, 'retries' => 0],
    ];
}

it('builds an initiate-transaction request and parses the form response', function () {
    Http::fake([
        'paynow.test/interface/initiatetransaction' => Http::response(
            'status=Ok&browserurl=https://paynow.test/pay/123&pollurl=https://paynow.test/poll/123',
            200,
            ['Content-Type' => 'application/x-www-form-urlencoded'],
        ),
    ]);

    $gateway = new PaynowGateway(paynowConfig());

    $result = $gateway->charge(new ChargeRequest(
        amount: Money::ofMajor('19.99', 'USD'),
        customer: new Customer(email: 'a@b.test'),
        reference: 'order-1',
        description: 'Test',
    ));

    expect($result->status)->toBe(PaymentStatus::PENDING)
        ->and($result->redirectUrl)->toBe('https://paynow.test/pay/123')
        ->and($result->pollUrl)->toBe('https://paynow.test/poll/123')
        ->and($result->gatewayReference)->toBe('https://paynow.test/poll/123');

    Http::assertSent(function ($request) {
        $body = $request->body();

        return str_contains($body, 'id=iid')
            && str_contains($body, 'reference=order-1')
            && str_contains($body, 'amount=19.99')
            && str_contains($body, 'hash=');
    });
});

it('verifies a webhook hash and parses payload to a normalised event', function () {
    $gateway = new PaynowGateway(paynowConfig());

    $fields = [
        'reference' => 'order-1',
        'paynowreference' => '12345',
        'amount' => '19.99',
        'status' => 'Paid',
        'pollurl' => 'https://paynow.test/poll/123',
    ];

    $unhashed = '';
    foreach ($fields as $v) {
        $unhashed .= $v;
    }
    $fields['hash'] = strtoupper(hash('sha512', $unhashed.'ikey'));

    $request = Request::create('/payments/webhooks/paynow', 'POST', $fields);

    expect($gateway->verifyWebhook($request))->toBeTrue();

    $event = $gateway->parseWebhook($request);
    expect($event)->not->toBeNull()
        ->and($event->status)->toBe(PaymentStatus::CAPTURED)
        ->and($event->reference)->toBe('order-1')
        ->and($event->gatewayReference)->toBe('12345');
});

it('rejects a webhook whose hash does not match', function () {
    $gateway = new PaynowGateway(paynowConfig());

    $request = Request::create('/payments/webhooks/paynow', 'POST', [
        'reference' => 'order-1',
        'status' => 'Paid',
        'hash' => str_repeat('A', 128),
    ]);

    expect(fn () => $gateway->verifyWebhook($request))
        ->toThrow(WebhookSignatureException::class);
});

it('maps Paynow statuses to normalised PaymentStatus values', function () {
    $gateway = new PaynowGateway(paynowConfig());

    $reflect = new ReflectionMethod($gateway, 'mapStatus');
    $reflect->setAccessible(true);

    expect($reflect->invoke($gateway, 'Paid'))->toBe(PaymentStatus::CAPTURED)
        ->and($reflect->invoke($gateway, 'Cancelled'))->toBe(PaymentStatus::CANCELLED)
        ->and($reflect->invoke($gateway, 'Created'))->toBe(PaymentStatus::PENDING);
});
