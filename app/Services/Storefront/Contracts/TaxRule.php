<?php

declare(strict_types=1);

namespace App\Services\Storefront\Contracts;

use App\Models\CheckoutSession;
use App\Services\Storefront\Data\PriceQuote;

/**
 * Strategy invoked during PriceCalculator's tax pass. Rules are
 * order-independent and additive: each returns a quote with its
 * tax lines appended.
 *
 * Implementations live in `app/Services/Storefront/Taxes/*` and are
 * registered in `config/storefront.php.pricing.tax_rules`.
 */
interface TaxRule
{
    public function apply(CheckoutSession $session, PriceQuote $quote): PriceQuote;
}
