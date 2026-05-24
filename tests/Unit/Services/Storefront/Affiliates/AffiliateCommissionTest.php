<?php

declare(strict_types=1);

use App\Models\AffiliateCode;
use App\Models\Order;
use App\Services\Storefront\Affiliates\AffiliateAttributor;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Pure-arithmetic tests for AffiliateAttributor::computeCommission
 * via a thin subclass that exposes the protected method. No DB needed.
 */
class ExposingAttributor extends AffiliateAttributor
{
    public function compute(AffiliateCode $code, Order $order): int
    {
        return $this->computeCommission($code, $order);
    }
}

function fakeOrder(int $subtotalCents, int $ticketCount): Order
{
    $order = new Order();
    $order->setRelation('items', collect([(object) ['quantity' => $ticketCount]]));
    $order->forceFill([
        'subtotal_cents' => $subtotalCents,
        'currency' => 'USD',
    ]);

    return $order;
}

function fakeCode(?int $bps, ?int $flatPerTicket): AffiliateCode
{
    $code = new AffiliateCode();
    $code->forceFill([
        'organization_id' => 1,
        'code' => 'TEST',
        'commission_bps' => $bps,
        'commission_flat_cents_per_ticket' => $flatPerTicket,
        'currency' => 'USD',
        'is_active' => true,
        'uses_count' => 0,
    ]);

    return $code;
}

it('computes flat-per-ticket commission', function () {
    $attributor = new ExposingAttributor();
    expect($attributor->compute(fakeCode(null, 500), fakeOrder(20000, 4)))->toBe(2000);
});

it('computes bps commission on subtotal', function () {
    $attributor = new ExposingAttributor();
    expect($attributor->compute(fakeCode(250, null), fakeOrder(20000, 4)))->toBe(500);
});

it('sums flat-per-ticket + bps when both configured', function () {
    $attributor = new ExposingAttributor();
    expect($attributor->compute(fakeCode(250, 500), fakeOrder(20000, 4)))->toBe(2500);
});

it('caps commission at order subtotal', function () {
    $attributor = new ExposingAttributor();
    expect($attributor->compute(fakeCode(null, 50000), fakeOrder(20000, 4)))->toBe(20000);
});

it('returns zero when both components are null/zero', function () {
    $attributor = new ExposingAttributor();
    expect($attributor->compute(fakeCode(null, null), fakeOrder(20000, 4)))->toBe(0);
});

it('handles zero subtotal cleanly', function () {
    $attributor = new ExposingAttributor();
    expect($attributor->compute(fakeCode(250, 500), fakeOrder(0, 4)))->toBe(0);
});

it('handles zero tickets cleanly', function () {
    $attributor = new ExposingAttributor();
    expect($attributor->compute(fakeCode(null, 500), fakeOrder(20000, 0)))->toBe(0);
});

it('isRedeemable returns false when inactive', function () {
    $code = fakeCode(250, null);
    $code->forceFill(['is_active' => false]);
    expect($code->isRedeemable())->toBeFalse();
});

it('isRedeemable returns false when expired', function () {
    $code = fakeCode(250, null);
    $code->forceFill(['expires_at' => \Carbon\CarbonImmutable::now()->subDay()]);
    expect($code->isRedeemable())->toBeFalse();
});

it('isRedeemable returns false when at max_uses', function () {
    $code = fakeCode(250, null);
    $code->forceFill(['max_uses' => 5, 'uses_count' => 5]);
    expect($code->isRedeemable())->toBeFalse();
});
