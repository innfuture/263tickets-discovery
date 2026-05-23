<?php

declare(strict_types=1);

use App\Services\Payments\Drivers\PaynowGateway;
use App\Services\Payments\Exceptions\GatewayNotConfiguredException;
use App\Services\Payments\PaymentManager;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set('payments.default', 'paynow');
    config()->set('payments.gateways.paynow', [
        'driver' => PaynowGateway::class,
        'enabled' => true,
        'label' => 'Paynow',
        'base_url' => 'https://example.test',
        'integration_id' => 'iid',
        'integration_key' => 'ikey',
        'express' => ['integration_id' => 'iid', 'integration_key' => 'ikey'],
        'supported_currencies' => ['USD'],
        'http' => ['timeout' => 5, 'connect_timeout' => 5, 'retries' => 0],
    ]);

    config()->set('payments.gateways.disabled_one', [
        'driver' => PaynowGateway::class,
        'enabled' => false,
    ]);
});

it('resolves the default gateway by identifier', function () {
    $manager = app(PaymentManager::class);

    expect($manager->default()->identifier())->toBe('paynow');
});

it('throws when asked for a disabled gateway', function () {
    $manager = app(PaymentManager::class);

    expect(fn () => $manager->gateway('disabled_one'))
        ->toThrow(GatewayNotConfiguredException::class);
});

it('throws when asked for an unknown gateway', function () {
    $manager = app(PaymentManager::class);

    expect(fn () => $manager->gateway('made-up'))
        ->toThrow(GatewayNotConfiguredException::class);
});

it('lists only enabled gateways', function () {
    $manager = app(PaymentManager::class);

    expect($manager->enabled())->toBe(['paynow']);
});

it('caches resolved driver instances', function () {
    $manager = app(PaymentManager::class);

    $a = $manager->gateway('paynow');
    $b = $manager->gateway('paynow');

    expect($a)->toBe($b);
});
