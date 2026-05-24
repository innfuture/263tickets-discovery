<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamps `X-Origin-Signature: t=<unix>,v1=<hex>` on outbound storefront
 * responses so the storefront-edge worker can verify the payload was
 * served by origin before stashing it in KV. Without this, a poisoned
 * upstream (compromised CDN, MITM in a misconfigured deployment) could
 * cause the edge to cache attacker-controlled JSON.
 *
 * No-op when the signing secret is unset — gracefully degrades to
 * pre-signing behavior in local / CI / single-region deployments.
 */
class SignStorefrontResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $secret = (string) config('storefront.signing_secret', '');
        if ($secret === '') {
            return $response;
        }

        $body = (string) $response->getContent();
        $timestamp = (string) time();
        $hex = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        $response->headers->set('X-Origin-Signature', "t={$timestamp},v1={$hex}");

        return $response;
    }
}
