<?php

declare(strict_types=1);

use App\Models\CheckoutSession;
use App\Models\Event;
use App\Services\Storefront\Data\PriceLine;
use App\Services\Storefront\Data\PriceQuote;
use App\Services\Storefront\Pricing\Dynamic\TimeOfDayPricingRule;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set('storefront.pricing.time_of_day_windows', []);
});

function tdSession(?CarbonImmutable $eventStart): CheckoutSession
{
    $session = new CheckoutSession();
    $session->forceFill(['currency' => 'USD']);
    if ($eventStart !== null) {
        $event = new Event();
        $event->forceFill(['starts_at' => $eventStart]);
        $session->setRelation('event', $event);
    } else {
        $session->setRelation('event', null);
    }

    return $session;
}

it('no-ops when no windows are configured', function () {
    $rule = new TimeOfDayPricingRule();
    $quote = PriceQuote::empty('USD')->withLine(new PriceLine(PriceLine::KIND_SUBTOTAL, 't', 10000, 'USD'));

    expect($rule->apply(tdSession(CarbonImmutable::now()->addDays(5)), $quote))->toBe($quote);
});

it('applies an early-bird discount when hours-until-event sits inside the window', function () {
    config()->set('storefront.pricing.time_of_day_windows', [
        ['hours_before_event_min' => 168, 'hours_before_event_max' => null, 'multiplier' => 0.90, 'label' => 'Early-bird'],
    ]);

    $rule = new TimeOfDayPricingRule();
    $quote = PriceQuote::empty('USD')->withLine(new PriceLine(PriceLine::KIND_SUBTOTAL, 't', 10000, 'USD'));
    $result = $rule->apply(tdSession(CarbonImmutable::now()->addDays(10)), $quote);

    $disc = collect($result->lines)->first(fn ($l) => $l->kind === PriceLine::KIND_DISCOUNT);
    expect($disc)->not->toBeNull();
    expect($disc->amountMinor)->toBe(1000);
    expect($disc->label)->toBe('Early-bird');
    expect($disc->meta['dynamic_rule'])->toBe('time_of_day');
});

it('applies a peak surge when inside the surge window', function () {
    config()->set('storefront.pricing.time_of_day_windows', [
        ['hours_before_event_min' => 0, 'hours_before_event_max' => 24, 'multiplier' => 1.05, 'label' => 'Peak surge'],
    ]);

    $rule = new TimeOfDayPricingRule();
    $quote = PriceQuote::empty('USD')->withLine(new PriceLine(PriceLine::KIND_SUBTOTAL, 't', 10000, 'USD'));
    $result = $rule->apply(tdSession(CarbonImmutable::now()->addHours(12)), $quote);

    $surge = collect($result->lines)
        ->where('kind', PriceLine::KIND_SUBTOTAL)
        ->first(fn ($l) => isset($l->meta['dynamic_rule']));
    expect($surge)->not->toBeNull();
    expect($surge->amountMinor)->toBe(500);
});

it('no-ops when the session has no resolvable event start', function () {
    config()->set('storefront.pricing.time_of_day_windows', [
        ['hours_before_event_min' => 0, 'hours_before_event_max' => 999, 'multiplier' => 1.10],
    ]);

    $rule = new TimeOfDayPricingRule();
    $quote = PriceQuote::empty('USD')->withLine(new PriceLine(PriceLine::KIND_SUBTOTAL, 't', 10000, 'USD'));

    expect($rule->apply(tdSession(null), $quote))->toBe($quote);
});

it('no-ops when multiplier is 1.0 (effectively disabled)', function () {
    config()->set('storefront.pricing.time_of_day_windows', [
        ['hours_before_event_min' => 0, 'hours_before_event_max' => 999, 'multiplier' => 1.0],
    ]);

    $rule = new TimeOfDayPricingRule();
    $quote = PriceQuote::empty('USD')->withLine(new PriceLine(PriceLine::KIND_SUBTOTAL, 't', 10000, 'USD'));

    expect($rule->apply(tdSession(CarbonImmutable::now()->addDay()), $quote))->toBe($quote);
});

it('uses the first matching window', function () {
    config()->set('storefront.pricing.time_of_day_windows', [
        ['hours_before_event_min' => 0, 'hours_before_event_max' => 24, 'multiplier' => 1.05, 'label' => 'Peak'],
        ['hours_before_event_min' => 0, 'hours_before_event_max' => 999, 'multiplier' => 0.95, 'label' => 'Whatever'],
    ]);

    $rule = new TimeOfDayPricingRule();
    $quote = PriceQuote::empty('USD')->withLine(new PriceLine(PriceLine::KIND_SUBTOTAL, 't', 10000, 'USD'));
    $result = $rule->apply(tdSession(CarbonImmutable::now()->addHours(6)), $quote);

    $surge = collect($result->lines)->first(fn ($l) => isset($l->meta['dynamic_rule']));
    expect($surge->label)->toBe('Peak');
});
