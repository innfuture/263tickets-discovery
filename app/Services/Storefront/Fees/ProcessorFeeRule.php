<?php

declare(strict_types=1);

namespace App\Services\Storefront\Fees;

use App\Models\CheckoutSession;
use App\Services\Storefront\Contracts\FeeRule;
use App\Services\Storefront\Data\PriceLine;
use App\Services\Storefront\Data\PriceQuote;

/**
 * Pass-through of the payment-processor fee. Eventbrite-style
 * "what your gateway charges us, you pay" line. The org controls
 * whether buyers see this with `storefront.processor_pass_through`.
 *
 * Skipped entirely when fees are eaten by the organizer.
 */
class ProcessorFeeRule implements FeeRule
{
    public function apply(CheckoutSession $session, PriceQuote $quote): PriceQuote
    {
        if ((string) config('storefront.pricing.fee_payer') === 'organizer') {
            return $quote;
        }

        $bps = (int) config('storefront.pricing.processor_fee_bps', 0);
        $flat = (int) config('storefront.pricing.processor_fee_flat_cents', 0);
        $base = max(0, $quote->subtotalMinor - $quote->discountMinor);
        $fee = (int) floor(($base * $bps) / 10_000) + $flat;

        if ($fee <= 0) {
            return $quote;
        }

        return $quote->withLine(new PriceLine(
            kind: PriceLine::KIND_FEE,
            label: __('storefront.lines.processing_fee'),
            amountMinor: $fee,
            currency: $quote->currency,
            meta: ['source' => 'processor'],
        ));
    }
}
