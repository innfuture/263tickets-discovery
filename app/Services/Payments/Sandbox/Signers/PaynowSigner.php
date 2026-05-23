<?php

declare(strict_types=1);

namespace App\Services\Payments\Sandbox\Signers;

use App\Models\SandboxMerchant;
use App\Models\SandboxWebhookOutbox;

/**
 * Paynow-style: form-encoded body with a `hash` field that is the
 * SHA-512 of the concatenated field values + integration key (upper-
 * cased). The integration key is reused as the webhook secret for
 * sandbox purposes.
 */
class PaynowSigner implements SignerInterface
{
    public function body(SandboxWebhookOutbox $event): string
    {
        $fields = $this->fields($event);

        return http_build_query($fields);
    }

    public function headers(SandboxWebhookOutbox $event, SandboxMerchant $merchant): array
    {
        // Paynow has no signature header — the `hash` field inside the
        // body is the signature. Just declare the content type so the
        // consumer parses it correctly.
        return ['Content-Type' => 'application/x-www-form-urlencoded'];
    }

    /**
     * @return array<string, string>
     */
    protected function fields(SandboxWebhookOutbox $event): array
    {
        $payload = (array) $event->payload;
        $secret = (string) $event->merchant->webhook_signing_secret;

        $fields = [
            'reference' => (string) ($payload['reference'] ?? ($event->transaction->reference ?? '')),
            'paynowreference' => (string) ($payload['paynowreference'] ?? ($event->transaction->provider_reference ?? '')),
            'amount' => number_format(((int) ($event->transaction->amount_minor ?? 0)) / 100, 2, '.', ''),
            'status' => $this->statusForType($event->type),
            'pollurl' => (string) ($payload['pollurl'] ?? ''),
        ];

        $unhashed = '';
        foreach ($fields as $v) {
            $unhashed .= $v;
        }
        $fields['hash'] = strtoupper(hash('sha512', $unhashed.$secret));

        return $fields;
    }

    protected function statusForType(string $type): string
    {
        return match ($type) {
            'payment.captured' => 'Paid',
            'payment.failed' => 'Failed',
            'payment.cancelled' => 'Cancelled',
            'refund.succeeded', 'refund.partial_succeeded' => 'Refunded',
            default => 'Created',
        };
    }
}
