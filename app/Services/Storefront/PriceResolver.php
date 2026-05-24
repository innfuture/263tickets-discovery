<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\TicketCategory;
use App\Services\Storefront\Exceptions\CurrencyMismatchException;

/**
 * Per-ticket-category unit price lookup with multi-currency support.
 *
 * Order of preference:
 *   1. A `ticket_currency_prices` row matching the session currency
 *   2. The category's `base_price` in `base_currency`, converted only
 *      when the session currency equals the base currency (no FX
 *      conversion in core — see the gaps document)
 *
 * Returned in minor units. Two-decimal currencies only — gateways
 * already make that assumption.
 *
 * Throws CurrencyMismatchException when the tier has no usable price
 * in the requested currency. Callers either surface this at the
 * /items step (preferred) or fall back to listing the tier as
 * unavailable.
 */
class PriceResolver
{
    public function resolveUnitPriceMinor(TicketCategory $category, string $currency): int
    {
        $currency = strtoupper($currency);

        $override = $category->currencyPrices
            ->first(fn ($p) => strtoupper((string) $p->currency_code) === $currency);

        if ($override) {
            return (int) round(((float) $override->price) * 100);
        }

        // Fall back to base price only if the session is in the base currency.
        if (strtoupper((string) $category->base_currency) === $currency) {
            return (int) round(((float) $category->base_price) * 100);
        }

        throw new CurrencyMismatchException(
            ticketCategoryId: (int) $category->id,
            cartCurrency: $currency,
        );
    }
}
