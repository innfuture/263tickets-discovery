<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Storefront\Contracts\CaptchaProvider;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public-storefront captcha gate. Applied to endpoints that create
 * new state (checkout session create, waitlist enrol, quote request,
 * refund request) — read-only endpoints stay open.
 *
 * Returns 422 with {error: 'captcha_required', provider, site_key}
 * so the front-end can render the right widget. With the null
 * provider bound (dev default) the gate is effectively a no-op.
 */
class EnsureCaptchaPassed
{
    public function __construct(protected CaptchaProvider $captcha) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->captcha->verify($request)) {
            return $next($request);
        }

        return response()->json([
            'error' => 'captcha_required',
            'provider' => $this->captcha->identifier(),
            'site_key' => $this->captcha->siteKey(),
        ], 422);
    }
}
