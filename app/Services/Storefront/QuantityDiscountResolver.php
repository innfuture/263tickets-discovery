<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\CheckoutSession;
use App\Models\QuantityDiscountRule;
use App\Services\Storefront\Contracts\DiscountResolver;
use App\Services\Storefront\Data\PriceLine;
use App\Services\Storefront\Data\PriceQuote;
use Illuminate\Support\Collection;

/**
 * Layered DiscountResolver: applies the buyer's promo code (delegated
 * to PromoCodeValidator) AND walks the per-org / per-event
 * `quantity_discount_rules` table for any tier whose cart quantity
 * meets the threshold.
 *
 * To opt in, bind this in a service provider:
 *
 *     $this->app->bind(DiscountResolver::class, QuantityDiscountResolver::class);
 *
 * Default binding stays as PromoCodeValidator alone.
 */
class QuantityDiscountResolver implements DiscountResolver
{
    public function __construct(protected PromoCodeValidator $promo) {}

    public function apply(CheckoutSession $session, PriceQuote $quote): PriceQuote
    {
        // Promo code first — preserves existing semantics.
        $quote = $this->promo->apply($session, $quote);

        $session->loadMissing('items');
        $eventId = (int) $session->event_id;

        $rules = QuantityDiscountRule::query()
            ->where('organisation_id', $session->organisation_id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('event_id')->orWhere('event_id', $eventId))
            ->get();

        foreach ($rules as $rule) {
            if (! $rule->isCurrentlyValid()) {
                continue;
            }

            $matchingLines = $session->items
                ->filter(fn ($item) => $this->itemMatches($item, $rule));

            $qty = (int) $matchingLines->sum('quantity');
            if ($qty < (int) $rule->min_quantity) {
                continue;
            }

            $subtotal = (int) $matchingLines->sum('line_total_cents');
            $discount = $this->computeDiscount($rule, $subtotal, $qty, $matchingLines);
            if ($discount <= 0) {
                continue;
            }

            $quote = $quote->withLine(new PriceLine(
                kind: PriceLine::KIND_DISCOUNT,
                label: 'Bulk discount: '.$rule->name,
                amountMinor: $discount,
                currency: $quote->currency,
                meta: [
                    'source' => 'quantity_discount',
                    'rule_id' => (int) $rule->id,
                ],
            ));
        }

        return $quote;
    }

    protected function itemMatches($item, QuantityDiscountRule $rule): bool
    {
        if ($rule->ticket_category_id !== null) {
            return (int) $item->ticket_category_id === (int) $rule->ticket_category_id;
        }

        // Event-wide rule — match any tier on the event.
        if ($rule->event_id !== null) {
            return true;
        }

        return false;
    }

    /** @param Collection<int, mixed> $lines */
    protected function computeDiscount(QuantityDiscountRule $rule, int $subtotal, int $qty, $lines): int
    {
        if ($rule->discount_fixed_cents !== null) {
            return min($subtotal, (int) $rule->discount_fixed_cents);
        }

        if ($rule->free_quantity !== null) {
            // BOGO style: each `min_quantity` purchased earns
            // `free_quantity` units at the cheapest unit_price.
            $cheapestUnit = (int) ($lines->min('unit_price_cents') ?? 0);
            $eligibleGroups = (int) floor($qty / (int) $rule->min_quantity);
            $freeUnits = $eligibleGroups * (int) $rule->free_quantity;

            return min($subtotal, $cheapestUnit * $freeUnits);
        }

        if ($rule->discount_percent !== null) {
            return (int) floor($subtotal * (int) $rule->discount_percent / 100);
        }

        return 0;
    }
}
