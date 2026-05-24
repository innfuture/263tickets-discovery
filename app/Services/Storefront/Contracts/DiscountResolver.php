<?php

declare(strict_types=1);

namespace App\Services\Storefront\Contracts;

use App\Models\CheckoutSession;
use App\Services\Storefront\Data\PriceQuote;

/**
 * Strategy that applies promo / discount lines on top of subtotal.
 * The default implementation reads the session's bound promo code;
 * an integrator could swap in a rule-based engine for bulk
 * "buy-2-get-1" mechanics.
 */
interface DiscountResolver
{
    public function apply(CheckoutSession $session, PriceQuote $quote): PriceQuote;
}
