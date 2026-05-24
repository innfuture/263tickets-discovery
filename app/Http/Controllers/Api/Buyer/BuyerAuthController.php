<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Services\Buyers\BuyerAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 *   POST /api/v1/buyer/auth/request-link    { email }
 *   POST /api/v1/buyer/auth/verify          { token } → bearer
 *   POST /api/v1/buyer/auth/logout                     (auth'd)
 *   POST /api/v1/buyer/auth/logout-all                 (auth'd)
 */
class BuyerAuthController extends Controller
{
    public function __construct(protected BuyerAuthService $auth) {}

    public function requestLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:191'],
        ]);

        // Always return 202, regardless of whether the email is on
        // file — prevents account enumeration via the magic-link UX.
        $this->auth->requestLink($validated['email'], $request->ip());

        return response()->json([
            'data' => ['message' => 'If that email is valid, a sign-in link is on its way.'],
        ], 202);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'size:48'],
        ]);

        try {
            $result = $this->auth->verifyToken(
                plaintext: $validated['token'],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );
        } catch (\Throwable $e) {
            return response()->json(['error' => 'invalid_or_expired'], 401);
        }

        return response()->json([
            'data' => [
                'bearer' => $result['bearer'],
                'buyer' => [
                    'uuid' => $result['buyer']->uuid,
                    'email' => $result['buyer']->email,
                    'name' => $result['buyer']->name,
                ],
                'expires_at' => $result['session']->expires_at?->toIso8601String(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $session = $request->attributes->get('buyer_session');
        if ($session) {
            $this->auth->revokeSession($session);
        }

        return response()->json(['data' => ['ok' => true]]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $buyer = $request->attributes->get('buyer');
        if ($buyer) {
            $this->auth->revokeAllSessions($buyer);
        }

        return response()->json(['data' => ['ok' => true]]);
    }
}
