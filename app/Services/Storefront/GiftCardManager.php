<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\AuditLog;
use App\Models\CheckoutSession;
use App\Models\GiftCard;
use App\Models\GiftCardRedemption;
use App\Models\Order;
use App\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sole writer to `gift_cards.balance_cents`. All redeem/void paths
 * route through here so the balance can never drift from the ledger
 * of `gift_card_redemptions` rows. Wrapped in a transaction with a
 * `lockForUpdate` to prevent concurrent redemptions overdrawing.
 */
class GiftCardManager
{
    public function issue(
        Organization $org,
        int $balanceCents,
        string $currency,
        string $source = GiftCard::SOURCE_PURCHASE,
        ?string $purchaserEmail = null,
        ?string $recipientEmail = null,
        ?string $recipientName = null,
        ?string $message = null,
        ?\DateTimeInterface $expiresAt = null,
    ): GiftCard {
        if ($balanceCents <= 0) {
            throw new RuntimeException('Initial balance must be > 0.');
        }

        // Retry on the rare code-collision case (32^12 keyspace, but
        // the friendly format collides more often than fully random).
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $card = GiftCard::create([
                    'organisation_id' => $org->uuid,
                    'code' => GiftCard::generateCode(),
                    'initial_balance_cents' => $balanceCents,
                    'balance_cents' => $balanceCents,
                    'currency' => strtoupper($currency),
                    'purchaser_email' => $purchaserEmail ? strtolower($purchaserEmail) : null,
                    'recipient_email' => $recipientEmail ? strtolower($recipientEmail) : null,
                    'recipient_name' => $recipientName,
                    'message' => $message,
                    'source' => $source,
                    'expires_at' => $expiresAt,
                ]);

                AuditLog::create([
                    'organization_id' => $org->id,
                    'actor_type' => 'system',
                    'action' => 'gift_card.issued',
                    'resource_type' => GiftCard::class,
                    'resource_id' => (string) $card->id,
                    'after' => [
                        'code' => $card->code,
                        'balance_cents' => $card->balance_cents,
                        'source' => $source,
                    ],
                ]);

                return $card;
            } catch (QueryException $e) {
                if (! str_contains($e->getMessage(), 'Duplicate') && ! str_contains($e->getMessage(), 'UNIQUE')) {
                    throw $e;
                }
                // Code collision — try again with a fresh code.
            }
        }

        throw new RuntimeException('Failed to mint a unique gift card code after 5 attempts.');
    }

    /**
     * Apply up to `$requestedCents` against this card. Returns the
     * GiftCardRedemption ledger row. Caller is responsible for
     * threading the redemption into a CheckoutSession discount line
     * (see GiftCardPaymentRule).
     */
    public function redeem(
        GiftCard $card,
        int $requestedCents,
        ?CheckoutSession $session = null,
        ?Order $order = null,
    ): GiftCardRedemption {
        if ($requestedCents <= 0) {
            throw new RuntimeException('Redeem amount must be > 0.');
        }

        return DB::transaction(function () use ($card, $requestedCents, $session, $order) {
            /** @var GiftCard $locked */
            $locked = GiftCard::query()->lockForUpdate()->findOrFail($card->id);
            if (! $locked->isActive()) {
                throw new RuntimeException('Gift card is not redeemable.');
            }

            $amount = min($requestedCents, (int) $locked->balance_cents);
            $locked->forceFill([
                'balance_cents' => $locked->balance_cents - $amount,
                'redeemed_at' => $locked->redeemed_at ?? Carbon::now(),
            ])->save();

            return GiftCardRedemption::create([
                'gift_card_id' => $locked->id,
                'checkout_session_id' => $session?->id,
                'order_id' => $order?->id,
                'amount_cents' => $amount,
                'currency' => $locked->currency,
                'action' => GiftCardRedemption::ACTION_REDEEMED,
            ]);
        });
    }

    /**
     * Reverse a prior redemption — returns funds to the card. Used
     * when a session expires/cancels after the gift card was applied.
     */
    public function voidRedemption(GiftCardRedemption $redemption): GiftCardRedemption
    {
        return DB::transaction(function () use ($redemption) {
            /** @var GiftCard $card */
            $card = GiftCard::query()->lockForUpdate()->findOrFail($redemption->gift_card_id);

            $card->forceFill([
                'balance_cents' => $card->balance_cents + (int) $redemption->amount_cents,
            ])->save();

            return GiftCardRedemption::create([
                'gift_card_id' => $card->id,
                'checkout_session_id' => $redemption->checkout_session_id,
                'order_id' => $redemption->order_id,
                'amount_cents' => -1 * (int) $redemption->amount_cents,
                'currency' => $redemption->currency,
                'action' => GiftCardRedemption::ACTION_VOIDED,
            ]);
        });
    }

    /**
     * Look up by code, returning null when not found / inactive.
     */
    public function findActive(string $code): ?GiftCard
    {
        $card = GiftCard::query()->where('code', strtoupper(trim($code)))->first();

        return $card?->isActive() ? $card : null;
    }
}
