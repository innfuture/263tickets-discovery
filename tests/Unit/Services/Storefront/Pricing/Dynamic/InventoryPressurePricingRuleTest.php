<?php

declare(strict_types=1);

use App\Models\CheckoutSession;
use App\Models\Event;
use App\Services\Storefront\Data\PriceLine;
use App\Services\Storefront\Data\PriceQuote;
use App\Services\Storefront\Pricing\Dynamic\InventoryPressurePricingRule;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set('storefront.pricing.inventory_pressure_tiers', []);
});

function inventorySession(?int $capacity, int $sold): CheckoutSession
{
    $session = new CheckoutSession();
    $session->forceFill(['currency' => 'USD']);
    if ($capacity === null) {
        $session->setRelation('event', null);
    } else {
        $event = new Event();
        $event->forceFill(['capacity' => $capacity, 'tickets_sold_count' => $sold]);
        $session->setRelation('event', $event);
    }

    return $session;
}

it('no-ops when no tiers configured', function () {
    $rule = new InventoryPressurePricingRule();
    $quote = PriceQuote::empty('USD')->withLine(new PriceLine(PriceLine::KIND_SUBTOTAL, 't', 10000, 'USD'));

    expect($rule->apply(inventorySession(1000, 850), $quote))->toBe($quote);
});

it('skips uncapped events', function () {
    config()->set('storefront.pricing.inventory_pressure_tiers', [
        ['sold_pct_min' => 70, 'multiplier' => 1.10],
    ]);

    $rule = new InventoryPressurePricingRule();
    $quote = PriceQuote::empty('USD')->withLine(new PriceLine(PriceLine::KIND_SUBTOTAL, 't', 10000, 'USD'));

    expect($rule->apply(inventorySession(null, 5000), $quote))->toBe($quote);
});

it('picks the highest matching tier', function () {
    config()->set('storefront.pricing.inventory_pressure_tiers', [
        ['sold_pct_min' => 70, 'multiplier' => 1.05],
        ['sold_pct_min' => 85, 'multiplier' => 1.10],
        ['sold_pct_min' => 95, 'multiplier' => 1.20],
    ]);

    $rule = new InventoryPressurePricingRule();
    $quote = PriceQuote::empty('USD')->withLine(new PriceLine(PriceLine::KIND_SUBTOTAL, 't', 10000, 'USD'));
    $result = $rule->apply(inventorySession(1000, 950), $quote);

    $surge = collect($result->lines)->first(fn ($l) => isset($l->meta['dynamic_rule']));
    expect($surge->amountMinor)->toBe(2000);
    expect($surge->label)->toContain('+20%');
});

it('no-ops below the lowest tier threshold', function () {
    config()->set('storefront.pricing.inventory_pressure_tiers', [
        ['sold_pct_min' => 70, 'multiplier' => 1.10],
    ]);

    $rule = new InventoryPressurePricingRule();
    $quote = PriceQuote::empty('USD')->withLine(new PriceLine(PriceLine::KIND_SUBTOTAL, 't', 10000, 'USD'));

    expect($rule->apply(inventorySession(1000, 100), $quote))->toBe($quote);
});

it('records sold_pct in line meta', function () {
    config()->set('storefront.pricing.inventory_pressure_tiers', [
        ['sold_pct_min' => 80, 'multiplier' => 1.15],
    ]);

    $rule = new InventoryPressurePricingRule();
    $quote = PriceQuote::empty('USD')->withLine(new PriceLine(PriceLine::KIND_SUBTOTAL, 't', 10000, 'USD'));
    $result = $rule->apply(inventorySession(1000, 870), $quote);

    $line = collect($result->lines)->first(fn ($l) => isset($l->meta['dynamic_rule']));
    expect($line->meta['sold_pct'])->toBe(87);
});
