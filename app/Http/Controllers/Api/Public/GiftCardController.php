<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\CheckoutSession;
use App\Services\Storefront\GiftCardManager;
use App\Services\Storefront\PriceCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 *   GET    /api/v1/public/gift-cards/{code}/balance      — read-only balance check
 *   POST   /api/v1/public/checkout/sessions/{uuid}/gift-card  — apply to cart
 *   DELETE /api/v1/public/checkout/sessions/{uuid}/gift-card  — remove from cart
 *
 * Application is stored under the session's attendee_data JSON so
 * GiftCardPaymentRule can find it during price recompute. No schema
 * change required.
 */
class GiftCardController extends Controller
{
    public function __construct(
        protected GiftCardManager $cards,
        protected PriceCalculator $prices,
    ) {}

    public function balance(string $code): JsonResponse
    {
        $card = $this->cards->findActive($code);
        if (! $card) {
            return response()->json(['error' => 'gift_card_not_found'], 404);
        }

        return response()->json([
            'data' => [
                'code' => $card->code,
                'balance_cents' => (int) $card->balance_cents,
                'currency' => $card->currency,
                'expires_at' => optional($card->expires_at)->toIso8601String(),
            ],
        ]);
    }

    public function apply(Request $request, string $uuid): JsonResponse
    {
        $session = CheckoutSession::query()->where('uuid', $uuid)->first();
        if (! $session || ! $session->isMutable()) {
            return response()->json(['error' => 'session_locked'], 409);
        }

        $validated = $request->validate(['code' => ['required', 'string', 'max:32']]);

        $card = $this->cards->findActive($validated['code']);
        if (! $card) {
            return response()->json(['error' => 'gift_card_not_found'], 422);
        }
        if ($card->currency !== $session->currency) {
            return response()->json([
                'error' => 'currency_mismatch',
                'gift_card_currency' => $card->currency,
                'session_currency' => $session->currency,
            ], 422);
        }

        $attendeeData = (array) ($session->attendee_data ?? []);
        $attendeeData['_gift_card_code'] = $card->code;
        $session->forceFill(['attendee_data' => $attendeeData])->save();

        $quote = $this->prices->recompute($session->refresh());

        return response()->json([
            'data' => [
                'gift_card' => [
                    'code' => $card->code,
                    'balance_cents' => (int) $card->balance_cents,
                ],
                'totals' => [
                    'total_cents' => $quote->totalMinor,
                    'discount_cents' => $quote->discountMinor,
                ],
            ],
        ]);
    }

    public function clear(string $uuid): JsonResponse
    {
        $session = CheckoutSession::query()->where('uuid', $uuid)->first();
        if (! $session || ! $session->isMutable()) {
            return response()->json(['error' => 'session_locked'], 409);
        }

        $attendeeData = (array) ($session->attendee_data ?? []);
        unset($attendeeData['_gift_card_code']);
        $session->forceFill(['attendee_data' => $attendeeData])->save();
        $this->prices->recompute($session->refresh());

        return response()->json(['data' => ['cleared' => true]]);
    }
}
