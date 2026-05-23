<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token gate for the HTTP-served sandbox surface. The shared
 * secret is `payments.sandbox.service_key`; when null/empty, the
 * middleware refuses every request — there is no anonymous mode for
 * the HTTP service because callers are other services, not browsers.
 *
 * Bearer comparison is constant-time to avoid token-length leakage.
 */
class SandboxServiceAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('payments.sandbox.service_key');
        if ($expected === '') {
            return response()->json(['error' => 'sandbox_service_disabled'], 403);
        }

        $header = (string) $request->header('Authorization', '');
        if (! str_starts_with($header, 'Bearer ')) {
            return response()->json(['error' => 'missing_bearer'], 401);
        }

        $supplied = substr($header, strlen('Bearer '));
        if (! hash_equals($expected, $supplied)) {
            return response()->json(['error' => 'invalid_bearer'], 401);
        }

        return $next($request);
    }
}
