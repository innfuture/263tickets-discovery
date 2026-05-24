<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Stakeholders\StakeholderAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token resolution for `/api/v1/stakeholder/*`. Attaches:
 *   $request->attributes->get('stakeholder')
 *   $request->attributes->get('stakeholder_session')
 */
class EnsureStakeholderAuth
{
    public function __construct(protected StakeholderAuthService $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();
        if (! $bearer) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        $session = $this->auth->resolveSession($bearer);
        if (! $session || ! $session->stakeholder) {
            return response()->json(['error' => 'session_invalid'], 401);
        }

        $request->attributes->set('stakeholder', $session->stakeholder);
        $request->attributes->set('stakeholder_session', $session);

        return $next($request);
    }
}
