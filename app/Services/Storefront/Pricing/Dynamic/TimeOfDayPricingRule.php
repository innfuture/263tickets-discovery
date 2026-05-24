<?php

declare(strict_types=1);

namespace App\Services\Storefront\Pricing\Dynamic;

use App\Models\CheckoutSession;
use App\Services\Storefront\Contracts\DynamicPricingRule;
use App\Services\Storefront\Data\PriceLine;
use App\Services\Storefront\Data\PriceQuote;
use Carbon\CarbonImmutable;

/**
 * Adjusts subtotal based on the buyer's local hour. Typical use:
 *   • Early-bird discount before event-day −7
 *   • Peak surge in the final 24h
 *   • Off-peak overnight discount
 *
 * Config (in storefront.pricing.time_of_day_windows):
 *   [
 *     ['hours_before_event_min' => 168, 'hours_before_event_max' => null, 'multiplier' => 0.90, 'label' => 'Early-bird'],
 *     ['hours_before_event_min' => 0,   'hours_before_event_max' => 24,   'multiplier' => 1.05, 'label' => 'Peak surge'],
 *   ]
 *
 * First matching window wins. If no event_starts_at is resolvable on
 * the session, the rule no-ops cleanly so checkout never breaks.
 */
class TimeOfDayPricingRule implements DynamicPricingRule
{
    public function identifier(): string
    {
        return 'time_of_day';
    }

    public function apply(CheckoutSession $session, PriceQuote $quote): PriceQuote
    {
        $windows = (array) config('storefront.pricing.time_of_day_windows', []);
        if ($windows === []) {
            return $quote;
        }

        $eventStart = $this->resolveEventStart($session);
        if ($eventStart === null) {
            return $quote;
        }

        $hoursUntilEvent = max(0, CarbonImmutable::now()->diffInMinutes($eventStart) / 60);

        $match = $this->matchWindow($windows, (int) $hoursUntilEvent);
        if ($match === null) {
            return $quote;
        }

        $multiplier = (float) ($match['multiplier'] ?? 1.0);
        if (abs($multiplier - 1.0) < 1e-6) {
            return $quote;
        }

        // Compute adjustment as a delta on subtotal — never on already-
        // applied tax/fee, that comes later in the pipeline.
        $base = $quote->subtotalMinor;
        $newBase = (int) round($base * $multiplier);
        $delta = $newBase - $base;

        if ($delta === 0) {
            return $quote;
        }

        $label = (string) ($match['label'] ?? ($delta < 0 ? 'Early-bird discount' : 'Peak surge'));

        return $quote->withLine(new PriceLine(
            kind: $delta < 0 ? PriceLine::KIND_DISCOUNT : PriceLine::KIND_SUBTOTAL,
            label: $label,
            amountMinor: abs($delta),
            currency: $quote->currency,
            meta: [
                'dynamic_rule' => $this->identifier(),
                'multiplier' => $multiplier,
                'hours_until_event' => (int) $hoursUntilEvent,
            ],
        ));
    }

    protected function resolveEventStart(CheckoutSession $session): ?CarbonImmutable
    {
        $session->loadMissing('event');
        $starts = $session->event?->starts_at;

        return $starts ? CarbonImmutable::parse($starts) : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $windows
     * @return array<string, mixed>|null
     */
    protected function matchWindow(array $windows, int $hoursUntilEvent): ?array
    {
        foreach ($windows as $window) {
            $min = $window['hours_before_event_min'] ?? null;
            $max = $window['hours_before_event_max'] ?? null;
            if ($min !== null && $hoursUntilEvent < (int) $min) {
                continue;
            }
            if ($max !== null && $hoursUntilEvent > (int) $max) {
                continue;
            }

            return $window;
        }

        return null;
    }
}
