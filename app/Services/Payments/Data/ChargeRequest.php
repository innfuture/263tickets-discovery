<?php

declare(strict_types=1);

namespace App\Services\Payments\Data;

/**
 * Provider-agnostic charge request. The PaymentManager normalises caller
 * input into this shape and hands it to whichever driver was selected.
 *
 *   reference        Caller-owned idempotency key (an order ID, ticket ID…).
 *                    Drivers also generate their own `clientCorrelator` /
 *                    `pollUrl` derived from this value.
 *   description      Human-readable line shown to the payer.
 *   returnUrl        Browser redirect target after payer completes flow.
 *   resultUrl        Server-to-server callback URL (webhooks).
 *   metadata         Free-form context echoed back through webhooks where
 *                    the gateway supports it.
 */
final class ChargeRequest
{
    public function __construct(
        public readonly Money $amount,
        public readonly Customer $customer,
        public readonly string $reference,
        public readonly string $description,
        public readonly ?string $returnUrl = null,
        public readonly ?string $resultUrl = null,
        /** @var array<string, scalar|null> */
        public readonly array $metadata = [],
    ) {}
}
