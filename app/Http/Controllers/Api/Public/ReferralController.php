<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\ReferralCode;
use Illuminate\Http\JsonResponse;

/**
 *   GET /api/v1/public/referral/{code}     — validate, return reward shape
 *
 * The FE persists a validated code in cart state and stamps it into
 * the checkout session's metadata at attendee-step. After the order
 * is paid, `CreditReferrerOnOrderPaid` issues the referrer's reward.
 */
class ReferralController extends Controller
{
    public function show(string $code): JsonResponse
    {
        $referral = ReferralCode::query()->where('code', strtoupper(trim($code)))->first();
        if (! $referral || ! $referral->isUsable()) {
            return response()->json(['error' => 'invalid_code'], 404);
        }

        return response()->json([
            'data' => [
                'code' => $referral->code,
                'reward_cents' => (int) $referral->reward_cents,
                'currency' => $referral->reward_currency,
            ],
        ]);
    }
}
