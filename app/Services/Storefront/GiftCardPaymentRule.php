<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\CheckoutSession;
use App\Services\Storefront\Contracts\FeeRule;
use App\Services\Storefront\Data\PriceLine;
use App\Services\Storefront\Data\PriceQuote;

/**
 * If the session has a `gift_card_id` attached (via attendee_data),
 * subtract its applicable balance from the post-discount, post-tax,
 * post-fee total. Effectively a payment method that runs at price-
 * compute time so the buyer sees the reduced "amount due now".
 *
 * Stored under attendee_data->_gift_card so we don't have to schema-
 * change `checkout_sessions` for an experimental feature.
 *
 * Listed late in the fee pipeline — runs *after* taxes + fees so
 * gift cards offset the grand total, not just the subtotal.
 */
class GiftCardPaymentRule implements FeeRule
{
    public function __construct(protected GiftCardManager $cards) {}

    public function apply(CheckoutSession $session, PriceQuote $quote): PriceQuote
    {
        $code = (string) (($session->attendee_data['_gift_card_code'] ?? '') ?: '');
        if ($code === '') {
            return $quote;
        }

        $card = $this->cards->findActive($code);
        if (! $card || $card->currency !== $quote->currency) {
            return $quote;
        }

        // Gift card covers up to the grand total; surplus stays on the card.
        $applicable = min($quote->totalMinor, (int) $card->balance_cents);
        if ($applicable <= 0) {
            return $quote;
        }

        return $quote->withLine(new PriceLine(
            kind: PriceLine::KIND_DISCOUNT,
            label: 'Gift card ('.$card->code.')',
            amountMinor: $applicable,
            currency: $quote->currency,
            meta: [
                'source' => 'gift_card',
                'gift_card_id' => (int) $card->id,
                'code' => $card->code,
            ],
        ));
    }
}
