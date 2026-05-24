<?php

declare(strict_types=1);

namespace App\Services\Storefront\Taxes;

use App\Models\CheckoutSession;
use App\Services\Storefront\Contracts\TaxRule;
use App\Services\Storefront\Data\PriceLine;
use App\Services\Storefront\Data\PriceQuote;

/**
 * Single jurisdiction-flat-rate VAT/GST. Reads the org's override or
 * falls back to `storefront.tax.default_rate_bps`. Compatible with
 * tax-inclusive pricing — when `tax_inclusive` is true the displayed
 * subtotal already contains the tax, so we surface the implied
 * portion as a separate line for the receipt rather than adding it
 * on top.
 *
 * For multi-jurisdiction rules, drop in a second TaxRule that
 * inspects `$session->buyer_country_code`.
 */
class FlatRateTaxRule implements TaxRule
{
    public function apply(CheckoutSession $session, PriceQuote $quote): PriceQuote
    {
        $bps = $this->resolveRateBps($session);
        if ($bps <= 0) {
            return $quote;
        }

        $base = max(0, $quote->subtotalMinor - $quote->discountMinor);
        $inclusive = (bool) config('storefront.tax.tax_inclusive', false);
        $label = (string) (config('storefront.tax.jurisdiction_label') ?? __('storefront.lines.tax'));

        if ($inclusive) {
            // tax_portion = base − base / (1 + rate)
            $rate = $bps / 10_000;
            $taxPortion = (int) round($base - ($base / (1 + $rate)));

            return $quote->withLine(new PriceLine(
                kind: PriceLine::KIND_TAX,
                label: __('storefront.lines.tax_inclusive', ['label' => $label]),
                amountMinor: 0, // already in subtotal
                currency: $quote->currency,
                meta: ['implied_cents' => $taxPortion, 'bps' => $bps, 'inclusive' => true],
            ));
        }

        $tax = (int) floor(($base * $bps) / 10_000);
        if ($tax <= 0) {
            return $quote;
        }

        return $quote->withLine(new PriceLine(
            kind: PriceLine::KIND_TAX,
            label: $label,
            amountMinor: $tax,
            currency: $quote->currency,
            meta: ['bps' => $bps, 'inclusive' => false],
        ));
    }

    protected function resolveRateBps(CheckoutSession $session): int
    {
        $orgOverride = optional($session->organization)
            ->organizationSetting?->get('storefront.tax_rate_bps');

        return is_numeric($orgOverride)
            ? (int) $orgOverride
            : (int) config('storefront.tax.default_rate_bps', 0);
    }
}
