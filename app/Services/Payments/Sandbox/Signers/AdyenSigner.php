<?php

declare(strict_types=1);

namespace App\Services\Payments\Sandbox\Signers;

use App\Models\SandboxMerchant;
use App\Models\SandboxWebhookOutbox;

/**
 * Adyen-style: HMAC-SHA256 over a pipe-joined list of fields, hex
 * decoded from the merchant's HMAC key. The signature is delivered
 * inside the body's `additionalData.hmacSignature` field, not a header.
 *
 * We use the merchant's webhook_signing_secret as both the HMAC key
 * (sandbox simplification) and a stand-in for the platform-wide key.
 */
class AdyenSigner implements SignerInterface
{
    public function body(SandboxWebhookOutbox $event): string
    {
        $merchant = $event->merchant;

        $core = [
            'pspReference' => (string) ($event->transaction?->provider_reference ?? $event->event_id),
            'originalReference' => '',
            'merchantAccountCode' => $merchant->slug,
            'merchantReference' => (string) ($event->transaction?->reference ?? ''),
            'amount.value' => (string) ($event->transaction?->amount_minor ?? 0),
            'amount.currency' => (string) ($event->transaction?->currency ?? 'USD'),
            'eventCode' => $this->eventCodeFor($event->type),
            'success' => $this->successFor($event->type),
        ];

        $signature = $this->signCore($core, (string) $merchant->webhook_signing_secret);

        $notification = $core + ['additionalData' => ['hmacSignature' => $signature]];

        return (string) json_encode(['notificationItems' => [['NotificationRequestItem' => $notification]]]);
    }

    public function headers(SandboxWebhookOutbox $event, SandboxMerchant $merchant): array
    {
        return ['Content-Type' => 'application/json'];
    }

    /**
     * @param  array<string, string>  $core
     */
    protected function signCore(array $core, string $secret): string
    {
        $escape = fn (string $v) => str_replace(['\\', ':'], ['\\\\', '\\:'], $v);
        $payload = implode(':', array_map($escape, array_values($core)));

        $key = @hex2bin($secret) ?: $secret;

        return base64_encode(hash_hmac('sha256', $payload, $key, true));
    }

    protected function eventCodeFor(string $type): string
    {
        return match ($type) {
            'payment.authorized' => 'AUTHORISATION',
            'payment.captured' => 'CAPTURE',
            'payment.failed' => 'AUTHORISATION',
            'payment.cancelled' => 'CANCELLATION',
            'refund.succeeded', 'refund.partial_succeeded' => 'REFUND',
            'charge.disputed' => 'NOTIFICATION_OF_CHARGEBACK',
            default => 'UNKNOWN',
        };
    }

    protected function successFor(string $type): string
    {
        return in_array($type, ['payment.failed'], true) ? 'false' : 'true';
    }
}
