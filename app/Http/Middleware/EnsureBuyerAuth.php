<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Buyers\BuyerAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Authorization: Bearer <session token>` lookup for the
 * `/api/v1/buyer/*` surface. Attaches the resolved Buyer + Session
 * to the request:
 *
 *   $request->attributes->get('buyer')
 *   $request->attributes->get('buyer_session')
 */
class EnsureBuyerAuth
{
    public function __construct(protected BuyerAuthService $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();
        if (! $bearer) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        $session = $this->auth->resolveSession($bearer);
        if (! $session || ! $session->buyer) {
            return response()->json(['error' => 'session_invalid'], 401);
        }

        $request->attributes->set('buyer', $session->buyer);
        $request->attributes->set('buyer_session', $session);

        return $next($request);
    }
}
