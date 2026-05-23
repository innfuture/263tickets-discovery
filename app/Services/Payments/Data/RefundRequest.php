<?php

declare(strict_types=1);

namespace App\Services\Payments\Data;

/**
 * Refund a previously-captured charge. Drivers locate the original by
 * `gatewayReference` (preferred — exact) or fall back to `reference`
 * (caller's idempotency key).
 *
 * `amount` left null means full refund; passing a smaller Money issues
 * a partial.
 */
final class RefundRequest
{
    public function __construct(
        public readonly string $reference,
        public readonly ?string $gatewayReference,
        public readonly ?Money $amount,
        public readonly string $reason,
        /** @var array<string, scalar|null> */
        public readonly array $metadata = [],
    ) {}
}
