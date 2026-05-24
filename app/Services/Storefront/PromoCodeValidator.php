<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\CheckoutSession;
use App\Models\TicketPromoCode;
use App\Services\Storefront\Contracts\DiscountResolver;
use App\Services\Storefront\Data\PriceLine;
use App\Services\Storefront\Data\PriceQuote;
use App\Services\Storefront\Exceptions\PromoCodeInvalidException;
use Illuminate\Support\Carbon;

/**
 * Validates and applies the promo code currently attached to a
 * session. Used in two places:
 *
 *   1. CheckoutController::applyPromo — validates by `code` string
 *      and attaches to the session if valid.
 *   2. PriceCalculator — invokes `apply()` during pricing.
 *
 * Validation enforces (in order): the code exists, is active, hasn't
 * expired, hasn't hit max_uses, and applies to at least one tier in
 * the cart. The first failure throws; we don't aggregate so the
 * surface message stays specific.
 *
 * Discount math: percent of (subtotal restricted to applicable lines).
 * For a code that's scoped to a single tier we only discount that
 * tier's portion of the subtotal, not the whole cart.
 */
class PromoCodeValidator implements DiscountResolver
{
    /**
     * Look up a code by string, validate it against the session's cart,
     * and return the promo row. Throws PromoCodeInvalidException on any
     * failure — the controller catches and shapes the user-facing error.
     */
    public function resolve(string $code, CheckoutSession $session): TicketPromoCode
    {
        $promo = TicketPromoCode::query()
            ->whereRaw('LOWER(code) = ?', [strtolower(trim($code))])
            ->first();

        if (! $promo) {
            throw new PromoCodeInvalidException('PROMO_NOT_FOUND', 'That code isn\'t recognised.');
        }

        if (! (bool) $promo->is_active) {
            throw new PromoCodeInvalidException('PROMO_INACTIVE', 'That code is no longer active.');
        }

        if ($promo->expires_at instanceof Carbon && $promo->expires_at->isPast()) {
            throw new PromoCodeInvalidException('PROMO_EXPIRED', 'That code has expired.');
        }

        if ($promo->max_uses !== null && (int) $promo->uses_count >= (int) $promo->max_uses) {
            throw new PromoCodeInvalidException('PROMO_EXHAUSTED', 'That code has been fully redeemed.');
        }

        $appliesToCart = $session->items
            ->contains(fn ($item) => (int) $item->ticket_category_id === (int) $promo->ticket_category_id);

        if (! $appliesToCart) {
            throw new PromoCodeInvalidException('PROMO_NOT_APPLICABLE', 'That code doesn\'t apply to anything in your cart.');
        }

        return $promo;
    }

    /**
     * Applied by PriceCalculator. Computes the discount against just
     * the tier(s) the code is scoped to, never the whole cart.
     */
    public function apply(CheckoutSession $session, PriceQuote $quote): PriceQuote
    {
        if (! $session->promo_code_id) {
            return $quote;
        }

        $promo = TicketPromoCode::query()->find($session->promo_code_id);
        if (! $promo || ! (bool) $promo->is_active) {
            return $quote;
        }

        $applicableMinor = $session->items
            ->filter(fn ($item) => (int) $item->ticket_category_id === (int) $promo->ticket_category_id)
            ->sum(fn ($item) => (int) $item->line_total_cents);

        if ($applicableMinor <= 0) {
            return $quote;
        }

        $discount = match ((string) $promo->discount_type) {
            'percentage' => (int) floor($applicableMinor * ((float) $promo->value) / 100),
            'fixed', 'flat' => min($applicableMinor, (int) round(((float) $promo->value) * 100)),
            default => 0,
        };

        if ($discount <= 0) {
            return $quote;
        }

        return $quote->withLine(new PriceLine(
            kind: PriceLine::KIND_DISCOUNT,
            label: 'Promo: '.($session->promo_code_snapshot ?: $promo->code),
            amountMinor: $discount,
            currency: $quote->currency,
            meta: [
                'promo_code_id' => (int) $promo->id,
                'discount_type' => (string) $promo->discount_type,
            ],
        ));
    }
}
