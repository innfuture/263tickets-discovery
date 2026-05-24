<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\DeveloperAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates developer-portal mutations (key issue, key revoke, subscribe,
 * token rotation, account inspection) behind the per-account bootstrap
 * token issued at registration.
 *
 * The token travels as `Authorization: Bearer <plaintext>` and is
 * compared hash-to-hash against the stored sha256. Constant-time
 * compare avoids leaking token shape through timing.
 *
 * The matched DeveloperAccount is attached to the request as
 * `developer_account` so downstream controllers don't need to look it
 * up by uuid a second time.
 */
class EnsureDeveloperPortalToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $uuid = (string) $request->route('uuid');
        if ($uuid === '') {
            return response()->json(['error' => 'missing_account'], 400);
        }

        $account = DeveloperAccount::query()->where('uuid', $uuid)->first();
        if (! $account) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $header = (string) $request->bearerToken();
        if ($header === '') {
            return response()->json(['error' => 'missing_token'], 401);
        }

        $expected = (string) ($account->portal_bootstrap_token_hash ?? '');
        if ($expected === '') {
            return response()->json(['error' => 'account_not_bootstrapped'], 401);
        }

        if (! hash_equals($expected, hash('sha256', $header))) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        $request->attributes->set('developer_account', $account);

        return $next($request);
    }
}
