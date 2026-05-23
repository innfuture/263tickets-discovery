<?php

declare(strict_types=1);

namespace App\Services\Payments\Data;

use App\Enums\PaymentStatus;

/**
 * Normalised webhook payload produced by `HandlesWebhooks::parseWebhook()`.
 * Returned for every recognised event regardless of provider.
 *
 *   reference         Our caller-owned idempotency key, when we can recover it.
 *   gatewayReference  Provider's own ID.
 *   status            Mapped to one of our PaymentStatus values.
 *   eventType         Provider-defined tag, kept for audit (we don't switch on it).
 *   raw               Original decrypted payload.
 *
 * Drivers that cannot recover the caller reference (Pesepay's encrypted
 * callbacks before lookup) leave `reference` null — the handler then
 * resolves the row by `gatewayReference`.
 */
final class WebhookEvent
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly PaymentStatus $status,
        public readonly ?string $reference,
        public readonly ?string $gatewayReference,
        public readonly string $eventType,
        public readonly ?Money $amount = null,
        public readonly array $raw = [],
    ) {}
}
