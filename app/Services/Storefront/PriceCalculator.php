<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\CheckoutSession;
use App\Services\Storefront\Contracts\DiscountResolver;
use App\Services\Storefront\Contracts\FeeRule;
use App\Services\Storefront\Contracts\TaxRule;
use App\Services\Storefront\Data\PriceLine;
use App\Services\Storefront\Data\PriceQuote;
use Illuminate\Contracts\Container\Container;

/**
 * Recomputes a CheckoutSession's totals from scratch:
 *
 *   subtotal  ← sum of line totals from checkout_session_items
 *   discount  ← DiscountResolver (default: PromoCodeValidator)
 *   tax       ← every TaxRule in config('storefront.pricing.tax_rules')
 *   fee       ← every FeeRule in config('storefront.pricing.fee_rules')
 *   total     ← max(0, subtotal − discount + tax + fee)
 *
 * The session is mutated in place + saved. Caller (CheckoutController
 * action methods or the OrderFulfillment finaliser) gets the quote
 * back to render to the buyer / record on the order.
 *
 * Pipeline order is FIXED — discounts run before tax (so tax is on
 * the post-discount base), and fees run last so the buyer sees the
 * platform/processor cut on top of taxed-and-discounted lines, which
 * matches Eventbrite and Ticketmaster receipts.
 */
class PriceCalculator
{
    public function __construct(protected Container $container) {}

    public function recompute(CheckoutSession $session): PriceQuote
    {
        $session->loadMissing('items', 'addons.addon');

        $quote = PriceQuote::empty($session->currency);

        // ── subtotal: ticket lines ──────────────────────────────────
        foreach ($session->items as $item) {
            if ((int) $item->line_total_cents <= 0) {
                continue;
            }

            $quote = $quote->withLine(new PriceLine(
                kind: PriceLine::KIND_SUBTOTAL,
                label: __('storefront.lines.tickets'),
                amountMinor: (int) $item->line_total_cents,
                currency: (string) $item->currency,
                meta: [
                    'ticket_category_id' => (int) $item->ticket_category_id,
                    'quantity' => (int) $item->quantity,
                    'label_key' => 'storefront.lines.tickets',
                ],
            ));
        }

        // ── subtotal: addon lines ──────────────────────────────────
        foreach ($session->addons as $addonRow) {
            if ((int) $addonRow->line_total_cents <= 0) {
                continue;
            }
            $quote = $quote->withLine(new PriceLine(
                kind: PriceLine::KIND_SUBTOTAL,
                label: (string) ($addonRow->addon?->name ?? 'Add-on'),
                amountMinor: (int) $addonRow->line_total_cents,
                currency: (string) $addonRow->currency,
                meta: [
                    'event_addon_id' => (int) $addonRow->event_addon_id,
                    'quantity' => (int) $addonRow->quantity,
                    'source' => 'addon',
                ],
            ));
        }

        // ── discount ────────────────────────────────────────────────
        $quote = $this->container->make(DiscountResolver::class)->apply($session, $quote);

        // ── tax ─────────────────────────────────────────────────────
        foreach ((array) config('storefront.pricing.tax_rules', []) as $ruleClass) {
            if (! is_string($ruleClass) || ! class_exists($ruleClass)) {
                continue;
            }
            $rule = $this->container->make($ruleClass);
            if ($rule instanceof TaxRule) {
                $quote = $rule->apply($session, $quote);
            }
        }

        // ── fees ────────────────────────────────────────────────────
        foreach ((array) config('storefront.pricing.fee_rules', []) as $ruleClass) {
            if (! is_string($ruleClass) || ! class_exists($ruleClass)) {
                continue;
            }
            $rule = $this->container->make($ruleClass);
            if ($rule instanceof FeeRule) {
                $quote = $rule->apply($session, $quote);
            }
        }

        // ── persist back to the session ────────────────────────────
        $session->forceFill([
            'subtotal_cents' => $quote->subtotalMinor,
            'discount_cents' => $quote->discountMinor,
            'tax_cents' => $quote->taxMinor,
            'fee_cents' => $quote->feeMinor,
            'total_cents' => $quote->totalMinor,
        ])->save();

        return $quote;
    }
}
