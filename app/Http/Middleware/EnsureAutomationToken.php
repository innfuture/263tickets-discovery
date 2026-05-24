<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AutomationToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Scoped bearer-token authentication for `/api/v1/automations/*`.
 * Token is sent as `Authorization: Bearer aut_<48 chars>`. Each route
 * binds via `middleware('automation.token:<scope>')` to require a
 * specific scope on the token.
 *
 * On success, attaches the resolved token + organization to the
 * request as `$request->automation_token` and `$request->automation_org`
 * so controllers can reach them without an extra lookup.
 */
class EnsureAutomationToken
{
    public function handle(Request $request, Closure $next, ?string $requiredScope = null): Response
    {
        $bearer = $request->bearerToken();
        if (! $bearer || ! str_starts_with($bearer, AutomationToken::TOKEN_PREFIX)) {
            return $this->reject('missing_token');
        }

        $token = AutomationToken::query()
            ->where('secret_hash', hash('sha256', $bearer))
            ->with('organization')
            ->first();

        if (! $token || ! $token->isActive()) {
            return $this->reject('invalid_token');
        }

        if ($requiredScope !== null && ! $token->hasScope($requiredScope)) {
            return $this->reject('insufficient_scope', extras: ['required_scope' => $requiredScope]);
        }

        $token->forceFill([
            'last_used_at' => Carbon::now(),
            'last_used_ip' => $request->ip(),
        ])->saveQuietly();

        $request->attributes->set('automation_token', $token);
        $request->attributes->set('automation_org', $token->organization);

        return $next($request);
    }

    /** @param array<string, mixed> $extras */
    protected function reject(string $code, array $extras = []): Response
    {
        return response()->json(array_merge([
            'error' => $code,
        ], $extras), 401);
    }
}
