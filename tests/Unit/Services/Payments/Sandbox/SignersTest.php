<?php

declare(strict_types=1);

use App\Models\SandboxMerchant;
use App\Models\SandboxTransaction;
use App\Models\SandboxWebhookOutbox;
use App\Services\Payments\Sandbox\Signers\AdyenSigner;
use App\Services\Payments\Sandbox\Signers\PaynowSigner;
use App\Services\Payments\Sandbox\Signers\SignerRegistry;
use App\Services\Payments\Sandbox\Signers\StripeSigner;
use Tests\TestCase;

uses(TestCase::class);

function makeEventStub(string $type = 'payment.captured', string $emulate = 'stripe'): SandboxWebhookOutbox
{
    $merchant = new SandboxMerchant([
        'slug' => 'm',
        'name' => 'Stub merchant',
        'webhook_signing_secret' => 'whsec_test_'.str_repeat('a', 32),
    ]);
    $merchant->id = 1;

    $event = new SandboxWebhookOutbox([
        'event_id' => 'evt_sbx_test',
        'type' => $type,
        'emulate' => $emulate,
        'payload' => ['reference' => 'ord_1', 'amount' => 1999, 'currency' => 'USD'],
        'scheduled_for' => now(),
    ]);
    $event->setRelation('merchant', $merchant);

    return $event;
}

it('Stripe signer produces an HMAC-SHA256 signature verifiable with the merchant secret', function () {
    $event = makeEventStub();
    $signer = new StripeSigner;

    $body = $signer->body($event);
    $headers = $signer->headers($event, $event->merchant);

    expect($headers['Stripe-Signature'])->toMatch('/^t=\d+,v1=[a-f0-9]{64}$/');

    // Header shape: t={ts},v1={sig}. Pull both halves directly so we
    // don't have to reason about preg_split's empty-string handling.
    preg_match('/^t=(\d+),v1=([a-f0-9]+)$/', $headers['Stripe-Signature'], $m);
    [, $ts, $deliveredSig] = $m;

    $expected = hash_hmac('sha256', "{$ts}.{$body}", $event->merchant->webhook_signing_secret);

    expect(hash_equals($expected, $deliveredSig))->toBeTrue();
});

it('Stripe signer embeds standard event envelope fields', function () {
    $body = (new StripeSigner)->body(makeEventStub('refund.succeeded'));
    $decoded = json_decode($body, true);

    expect($decoded)
        ->toHaveKeys(['id', 'object', 'type', 'created', 'livemode', 'data'])
        ->and($decoded['object'])->toBe('event')
        ->and($decoded['type'])->toBe('refund.succeeded')
        ->and($decoded['livemode'])->toBeFalse();
});

it('Paynow signer concatenates fields + secret and uppercases the SHA-512', function () {
    $event = makeEventStub('payment.captured', 'paynow');
    $event->setRelation('transaction', new SandboxTransaction([
        'reference' => 'ord_1',
        'provider_reference' => 'sbx_xyz',
        'amount_minor' => 1999,
        'currency' => 'USD',
    ]));

    $body = (new PaynowSigner)->body($event);
    parse_str($body, $parsed);

    expect($parsed['hash'])->toMatch('/^[A-F0-9]{128}$/');

    $unhashed = $parsed['reference'].$parsed['paynowreference'].$parsed['amount'].$parsed['status'].$parsed['pollurl'];
    $expected = strtoupper(hash('sha512', $unhashed.$event->merchant->webhook_signing_secret));

    expect($parsed['hash'])->toBe($expected);
});

it('Adyen signer wraps notifications in notificationItems and signs with HMAC', function () {
    $event = makeEventStub('payment.captured', 'adyen');
    $event->setRelation('transaction', new SandboxTransaction([
        'reference' => 'ord_1',
        'provider_reference' => 'PSP_test',
        'amount_minor' => 1999,
        'currency' => 'USD',
    ]));

    $body = (new AdyenSigner)->body($event);
    $decoded = json_decode($body, true);

    expect($decoded)->toHaveKey('notificationItems')
        ->and($decoded['notificationItems'][0]['NotificationRequestItem']['eventCode'])->toBe('CAPTURE')
        ->and($decoded['notificationItems'][0]['NotificationRequestItem']['additionalData']['hmacSignature'])
        ->toMatch('/^[A-Za-z0-9+\/]+=*$/');
});

it('SignerRegistry resolves known emulators and falls back to Stripe-style', function () {
    $registry = new SignerRegistry;

    expect($registry->for('stripe'))->toBeInstanceOf(StripeSigner::class)
        ->and($registry->for('paynow'))->toBeInstanceOf(PaynowSigner::class)
        ->and($registry->for('adyen'))->toBeInstanceOf(AdyenSigner::class)
        ->and($registry->for('unknown-provider'))->toBeInstanceOf(StripeSigner::class);
});
