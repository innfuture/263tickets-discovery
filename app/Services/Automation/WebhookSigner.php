<?php

declare(strict_types=1);

namespace App\Services\Automation;

/**
 * Stripe-style signed-header generator. Identical scheme to the
 * scanner + payment webhooks we already ship — one mental model
 * across the platform. n8n's "Webhook" trigger node accepts this
 * header verbatim; a tiny Code node verifies it.
 *
 *   X-Example-App-Signature: t=<unix>,v1=<hex>
 *   hex = hmac_sha256("<t>.<rawBody>", secret)
 *
 * Verification uses constant-time compare against `hash_equals`.
 */
class WebhookSigner
{
    public const HEADER = 'X-Example-App-Signature';

    public function sign(string $rawBody, string $secret, ?int $timestamp = null): string
    {
        $t = $timestamp ?? time();
        $hex = hash_hmac('sha256', $t.'.'.$rawBody, $secret);

        return "t={$t},v1={$hex}";
    }

    public function verify(
        string $rawBody,
        string $headerValue,
        string $secret,
        int $toleranceSeconds = 300,
    ): bool {
        $parts = [];
        foreach (explode(',', $headerValue) as $kv) {
            $pair = explode('=', trim($kv), 2);
            if (count($pair) === 2) {
                $parts[trim($pair[0])] = trim($pair[1]);
            }
        }

        $t = isset($parts['t']) ? (int) $parts['t'] : 0;
        $v1 = $parts['v1'] ?? '';

        if ($t <= 0 || $v1 === '') {
            return false;
        }
        if (abs(time() - $t) > $toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', $t.'.'.$rawBody, $secret);

        return hash_equals($expected, $v1);
    }
}
