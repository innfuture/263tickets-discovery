<?php

declare(strict_types=1);

namespace App\Services\Storefront\Pricing\Dynamic;

use App\Models\CheckoutSession;
use App\Services\Storefront\Contracts\DynamicPricingRule;
use App\Services\Storefront\Data\PriceLine;
use App\Services\Storefront\Data\PriceQuote;

/**
 * Surge pricing when inventory is running low. Configured as a
 * stepped curve: at N% sold, apply multiplier M.
 *
 * Config (storefront.pricing.inventory_pressure_tiers):
 *   [
 *     ['sold_pct_min' => 70, 'multiplier' => 1.05],
 *     ['sold_pct_min' => 85, 'multiplier' => 1.10],
 *     ['sold_pct_min' => 95, 'multiplier' => 1.20],
 *   ]
 *
 * Highest matching tier wins. Skipped entirely when the event has no
 * declared capacity (unlimited tickets) or when sold_count is
 * unavailable.
 *
 * Buyers see "Limited availability surcharge +5%" on the breakdown
 * — never the raw percentage thresholds, which would invite gaming.
 */
class InventoryPressurePricingRule implements DynamicPricingRule
{
    public function identifier(): string
    {
        return 'inventory_pressure';
    }

    public function apply(CheckoutSession $session, PriceQuote $quote): PriceQuote
    {
        $tiers = (array) config('storefront.pricing.inventory_pressure_tiers', []);
        if ($tiers === []) {
            return $quote;
        }

        $session->loadMissing('event');
        $event = $session->event;
        if (! $event || ! $event->capacity || (int) $event->capacity <= 0) {
            return $quote;
        }

        $sold = (int) ($event->tickets_sold_count ?? 0);
        $soldPct = (int) floor(($sold / (int) $event->capacity) * 100);

        $multiplier = $this->resolveMultiplier($tiers, $soldPct);
        if ($multiplier === null || abs($multiplier - 1.0) < 1e-6) {
            return $quote;
        }

        $base = $quote->subtotalMinor;
        $newBase = (int) round($base * $multiplier);
        $delta = $newBase - $base;

        if ($delta === 0) {
            return $quote;
        }

        $surge = (int) round(($multiplier - 1.0) * 100);
        $label = $delta > 0
            ? "Limited availability surcharge +{$surge}%"
            : "Restock discount {$surge}%";

        return $quote->withLine(new PriceLine(
            kind: $delta < 0 ? PriceLine::KIND_DISCOUNT : PriceLine::KIND_SUBTOTAL,
            label: $label,
            amountMinor: abs($delta),
            currency: $quote->currency,
            meta: [
                'dynamic_rule' => $this->identifier(),
                'multiplier' => $multiplier,
                'sold_pct' => $soldPct,
            ],
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $tiers
     */
    protected function resolveMultiplier(array $tiers, int $soldPct): ?float
    {
        usort($tiers, fn (array $a, array $b): int => (int) $b['sold_pct_min'] - (int) $a['sold_pct_min']);
        foreach ($tiers as $tier) {
            if ($soldPct >= (int) ($tier['sold_pct_min'] ?? 0)) {
                return (float) ($tier['multiplier'] ?? 1.0);
            }
        }

        return null;
    }
}
