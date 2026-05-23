<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Jobs\Payments\ProcessWebhookEventJob;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookEvent;
use App\Services\Payments\Drivers\PaynowGateway;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    config()->set('payments.default', 'paynow');
    config()->set('payments.gateways.paynow', [
        'driver' => PaynowGateway::class,
        'enabled' => true,
        'label' => 'Paynow',
        'base_url' => 'https://paynow.test',
        'integration_id' => 'iid',
        'integration_key' => 'ikey',
        'express' => ['integration_id' => 'iid', 'integration_key' => 'ikey'],
        'supported_currencies' => ['USD'],
        'http' => ['timeout' => 5, 'connect_timeout' => 5, 'retries' => 0],
    ]);
});

function paynowSignedPayload(array $fields, string $key = 'ikey'): array
{
    $unhashed = '';
    foreach ($fields as $v) {
        $unhashed .= $v;
    }
    $fields['hash'] = strtoupper(hash('sha512', $unhashed.$key));

    return $fields;
}

it('accepts a signed Paynow webhook, stores it, and dispatches processing', function () {
    Bus::fake();

    $fields = paynowSignedPayload([
        'reference' => 'order-1',
        'paynowreference' => 'PN123',
        'amount' => '19.99',
        'status' => 'Paid',
        'pollurl' => 'https://paynow.test/poll/1',
    ]);

    $response = $this->post('/payments/webhooks/paynow', $fields);

    $response->assertOk()->assertJsonStructure(['status', 'event_id']);

    expect(PaymentWebhookEvent::count())->toBe(1);

    $row = PaymentWebhookEvent::first();
    expect($row->gateway)->toBe('paynow')
        ->and($row->status)->toBe(PaymentStatus::CAPTURED->value)
        ->and($row->reference)->toBe('order-1');

    Bus::assertDispatched(ProcessWebhookEventJob::class);
});

it('returns 401 for a Paynow webhook with a wrong hash', function () {
    $response = $this->post('/payments/webhooks/paynow', [
        'reference' => 'order-2',
        'status' => 'Paid',
        'hash' => str_repeat('F', 128),
    ]);

    $response->assertStatus(401);
    expect(PaymentWebhookEvent::count())->toBe(0);
});

it('returns 200 duplicate without re-dispatch for repeat deliveries', function () {
    Bus::fake();

    $fields = paynowSignedPayload([
        'reference' => 'order-3',
        'paynowreference' => 'PN999',
        'amount' => '5.00',
        'status' => 'Paid',
        'pollurl' => 'https://paynow.test/poll/3',
    ]);

    $this->post('/payments/webhooks/paynow', $fields)->assertOk();
    $second = $this->post('/payments/webhooks/paynow', $fields);

    $second->assertOk()->assertJson(['status' => 'duplicate']);
    expect(PaymentWebhookEvent::count())->toBe(1);

    Bus::assertDispatchedTimes(ProcessWebhookEventJob::class, 1);
});

it('applies the webhook to the matching PaymentTransaction when the job runs', function () {
    $transaction = PaymentTransaction::create([
        'gateway' => 'paynow',
        'reference' => 'order-4',
        'status' => PaymentStatus::PENDING->value,
        'amount_minor' => 500,
        'currency' => 'USD',
    ]);

    $fields = paynowSignedPayload([
        'reference' => 'order-4',
        'paynowreference' => 'PN-XYZ',
        'amount' => '5.00',
        'status' => 'Paid',
        'pollurl' => 'https://paynow.test/poll/4',
    ]);

    $this->post('/payments/webhooks/paynow', $fields)->assertOk();

    $event = PaymentWebhookEvent::first();
    (new ProcessWebhookEventJob($event->id))->handle();

    $transaction->refresh();
    expect($transaction->status)->toBe(PaymentStatus::CAPTURED->value)
        ->and($transaction->settled_at)->not->toBeNull();

    $event->refresh();
    expect($event->processing_status)->toBe('processed');
});

it('returns 404 when the gateway segment does not map to a configured driver', function () {
    $response = $this->post('/payments/webhooks/unknown-gateway', ['anything' => 'goes']);
    $response->assertStatus(404);
});
