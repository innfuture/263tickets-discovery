<?php

declare(strict_types=1);

namespace App\Services\Storefront\Contracts;

use App\Models\CheckoutSession;
use App\Services\Storefront\Data\PriceQuote;

/**
 * Runs between subtotal-build and discount in the PriceCalculator
 * pipeline. Implementations either surcharge (positive line, kind
 * subtotal) or discount (positive line, kind discount) the running
 * quote based on dynamic context — time of day, inventory pressure,
 * loyalty tier, ZIP code, etc.
 *
 * Rules are order-independent and additive: each returns a new quote
 * with its lines appended. Implementations live in
 * `app/Services/Storefront/Pricing/Dynamic/*` and are registered in
 * `config/storefront.pricing.dynamic_rules`.
 *
 * Buyer transparency rule: every adjustment emits a PriceLine whose
 * `meta` carries `dynamic_rule` (class name short id) + `factor`
 * (multiplier) so the UI can render "Early-bird 10% off" or "Peak
 * surge +5%" in the breakdown.
 */
interface DynamicPricingRule
{
    public function apply(CheckoutSession $session, PriceQuote $quote): PriceQuote;

    /**
     * Short identifier for telemetry + the buyer-facing label. Kept
     * separate from the FQCN so rules can be renamed without changing
     * what buyers see on receipts.
     */
    public function identifier(): string;
}
