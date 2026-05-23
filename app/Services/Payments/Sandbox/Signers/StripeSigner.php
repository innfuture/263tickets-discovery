<?php

declare(strict_types=1);

namespace App\Services\Payments\Sandbox\Signers;

use App\Models\SandboxMerchant;
use App\Models\SandboxWebhookOutbox;

/**
 * Emulates Stripe-Signature: `t={ts},v1={hmac}` where hmac =
 * HMAC-SHA256("t.body", whsec) — verified the same way real Stripe
 * webhooks are. Lets consumers test their existing Stripe signature
 * verification against the sandbox.
 */
class StripeSigner implements SignerInterface
{
    public function body(SandboxWebhookOutbox $event): string
    {
        return (string) json_encode([
            'id' => $event->event_id,
            'object' => 'event',
            'type' => $event->type,
            'created' => $event->scheduled_for?->timestamp ?? time(),
            'livemode' => false,
            'data' => ['object' => $event->payload],
        ], JSON_UNESCAPED_SLASHES);
    }

    public function headers(SandboxWebhookOutbox $event, SandboxMerchant $merchant): array
    {
        $body = $this->body($event);
        $ts = $event->scheduled_for?->timestamp ?? time();
        $secret = (string) $merchant->webhook_signing_secret;
        $hmac = hash_hmac('sha256', "{$ts}.{$body}", $secret);

        return [
            'Content-Type' => 'application/json',
            'Stripe-Signature' => "t={$ts},v1={$hmac}",
        ];
    }
}
