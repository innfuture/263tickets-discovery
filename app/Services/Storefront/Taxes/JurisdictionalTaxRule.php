<?php

declare(strict_types=1);

namespace App\Services\Storefront\Taxes;

use App\Models\CheckoutSession;
use App\Services\Storefront\Contracts\TaxRule;
use App\Services\Storefront\Data\PriceLine;
use App\Services\Storefront\Data\PriceQuote;

/**
 * Per-country tax rule. Reads the rate from a map keyed by ISO-3166-1
 * alpha-2 country code in `config('storefront.tax.jurisdiction_rates')`:
 *
 *   'ZW' => 1500,   // 15% VAT
 *   'ZA' => 1500,   // 15% VAT
 *   'GB' => 2000,   // 20% VAT
 *
 * Falls back to `storefront.tax.default_rate_bps` if the buyer's
 * country isn't in the map. Drop this rule in alongside (or instead
 * of) `FlatRateTaxRule` in `config/storefront.php.pricing.tax_rules`.
 */
class JurisdictionalTaxRule implements TaxRule
{
    public function apply(CheckoutSession $session, PriceQuote $quote): PriceQuote
    {
        $bps = $this->resolveRateBps($session);
        if ($bps <= 0) {
            return $quote;
        }

        $base = max(0, $quote->subtotalMinor - $quote->discountMinor);
        $tax = (int) floor(($base * $bps) / 10_000);
        if ($tax <= 0) {
            return $quote;
        }

        $label = (string) config('storefront.tax.jurisdiction_label', 'Tax');
        $cc = strtoupper((string) $session->buyer_country_code);

        return $quote->withLine(new PriceLine(
            kind: PriceLine::KIND_TAX,
            label: $cc !== '' ? "{$label} ({$cc})" : $label,
            amountMinor: $tax,
            currency: $quote->currency,
            meta: ['bps' => $bps, 'country' => $cc],
        ));
    }

    protected function resolveRateBps(CheckoutSession $session): int
    {
        $rates = (array) config('storefront.tax.jurisdiction_rates', []);
        $cc = strtoupper((string) $session->buyer_country_code);

        if ($cc !== '' && isset($rates[$cc]) && is_numeric($rates[$cc])) {
            return (int) $rates[$cc];
        }

        return (int) config('storefront.tax.default_rate_bps', 0);
    }
}
