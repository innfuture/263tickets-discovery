<?php

declare(strict_types=1);

namespace App\Services\Storefront\Fees;

use App\Models\CheckoutSession;
use App\Services\Storefront\Contracts\FeeRule;
use App\Services\Storefront\Data\PriceLine;
use App\Services\Storefront\Data\PriceQuote;

/**
 * Platform take — what the SaaS retains per ticket sold. Applied as
 * (subtotal × bps / 10000) + flat. The org can override either via
 * `OrganizationSetting::for($org)->get('storefront.platform_fee_bps')`
 * which we fall back to defaults if unset.
 *
 * When `fee_payer` is 'organizer' the fee is omitted from the buyer's
 * total (org eats it from the payout) so we return the quote unchanged.
 */
class PlatformFeeRule implements FeeRule
{
    public function apply(CheckoutSession $session, PriceQuote $quote): PriceQuote
    {
        if ((string) config('storefront.pricing.fee_payer') === 'organizer') {
            return $quote;
        }

        $bps = $this->resolveBps($session);
        $flat = $this->resolveFlat($session);
        $taxable = max(0, $quote->subtotalMinor - $quote->discountMinor);

        $fee = (int) floor(($taxable * $bps) / 10_000) + $flat;
        if ($fee <= 0) {
            return $quote;
        }

        $split = (string) config('storefront.pricing.fee_payer') === 'split'
            ? (int) ceil($fee / 2)
            : $fee;

        return $quote->withLine(new PriceLine(
            kind: PriceLine::KIND_FEE,
            label: __('storefront.lines.service_fee'),
            amountMinor: $split,
            currency: $quote->currency,
            meta: ['source' => 'platform', 'bps' => $bps, 'flat' => $flat],
        ));
    }

    protected function resolveBps(CheckoutSession $session): int
    {
        $orgOverride = optional($session->organization)
            ->organizationSetting?->get('storefront.platform_fee_bps');

        return is_numeric($orgOverride)
            ? (int) $orgOverride
            : (int) config('storefront.pricing.platform_fee_bps', 0);
    }

    protected function resolveFlat(CheckoutSession $session): int
    {
        $orgOverride = optional($session->organization)
            ->organizationSetting?->get('storefront.platform_fee_flat_cents');

        return is_numeric($orgOverride)
            ? (int) $orgOverride
            : (int) config('storefront.pricing.platform_fee_flat_cents', 0);
    }
}
