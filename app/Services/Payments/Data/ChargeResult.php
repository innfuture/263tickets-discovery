<?php

declare(strict_types=1);

namespace App\Services\Payments\Data;

use App\Enums\PaymentStatus;

/**
 * What a driver returns after initiating a charge. `status` is the
 * normalised state — most providers respond PENDING immediately and
 * settle via webhook. `redirectUrl` and `pollUrl` are populated when
 * the gateway returns a hosted page or a status endpoint respectively.
 *
 * `gatewayReference` is the provider's own ID for the transaction
 * (Paynow's `pollurl`-token, Pesepay's `referenceNumber`, EcoCash's
 * `transactionReference`, Zimswitch's `id`). Persist it on the
 * PaymentTransaction so reconciliation jobs can look up status later.
 */
final class ChargeResult
{
    /**
     * @param  array<string, mixed>  $raw  Verbatim gateway response, for audit + debugging.
     */
    public function __construct(
        public readonly PaymentStatus $status,
        public readonly string $reference,
        public readonly ?string $gatewayReference = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $pollUrl = null,
        public readonly ?string $instructions = null,
        public readonly array $raw = [],
    ) {}
}
