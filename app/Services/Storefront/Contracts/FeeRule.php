<?php

declare(strict_types=1);

namespace App\Services\Storefront\Contracts;

use App\Models\CheckoutSession;
use App\Services\Storefront\Data\PriceQuote;

/**
 * Strategy invoked during PriceCalculator's fee pass. Fees are
 * order-independent and additive. The rule decides — based on
 * config — whether to add the line at all (e.g. buyer-pays vs.
 * organizer-pays).
 */
interface FeeRule
{
    public function apply(CheckoutSession $session, PriceQuote $quote): PriceQuote;
}
