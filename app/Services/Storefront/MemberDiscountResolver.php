<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\BuyerMembership;
use App\Models\CheckoutSession;
use App\Services\Storefront\Contracts\DiscountResolver;
use App\Services\Storefront\Data\PriceLine;
use App\Services\Storefront\Data\PriceQuote;

/**
 * Layered resolver — runs the configured upstream first (default
 * PromoCodeValidator), then applies member-tier discounts from any
 * active BuyerMembership matching the buyer's email.
 *
 * Membership benefit shape (in `memberships.benefits` JSON):
 *
 *   { "discount_percent": 10,
 *     "discount_currency": "USD",
 *     "applies_to": "all" | "tickets" | "addons" }
 *
 * Wire in a downstream provider with:
 *   $this->app->bind(DiscountResolver::class, MemberDiscountResolver::class);
 *
 * Default binding stays as PromoCodeValidator alone.
 */
class MemberDiscountResolver implements DiscountResolver
{
    public function __construct(protected PromoCodeValidator $promo) {}

    public function apply(CheckoutSession $session, PriceQuote $quote): PriceQuote
    {
        $quote = $this->promo->apply($session, $quote);

        $email = (string) ($session->buyer_email ?? '');
        if ($email === '') {
            return $quote;
        }

        $membership = BuyerMembership::query()
            ->where('organisation_id', $session->organisation_id)
            ->where('member_email', strtolower($email))
            ->where('status', BuyerMembership::STATUS_ACTIVE)
            ->where('ends_at', '>=', now())
            ->first();

        if (! $membership) {
            return $quote;
        }

        $benefits = (array) ($membership->benefits ?? []);
        $percent = (int) ($benefits['discount_percent'] ?? 0);
        if ($percent <= 0) {
            return $quote;
        }

        $currencyConstraint = (string) ($benefits['discount_currency'] ?? $session->currency);
        if (strtoupper($currencyConstraint) !== strtoupper($session->currency)) {
            return $quote;
        }

        $base = max(0, $quote->subtotalMinor - $quote->discountMinor);
        $discount = (int) floor($base * $percent / 100);
        if ($discount <= 0) {
            return $quote;
        }

        return $quote->withLine(new PriceLine(
            kind: PriceLine::KIND_DISCOUNT,
            label: 'Member discount ('.$percent.'%)',
            amountMinor: $discount,
            currency: $quote->currency,
            meta: [
                'source' => 'membership',
                'membership_uuid' => $membership->uuid,
                'percent' => $percent,
            ],
        ));
    }
}
