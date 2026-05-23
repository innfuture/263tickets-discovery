<?php

declare(strict_types=1);

namespace App\Services\Payments\Data;

use App\Enums\PaymentStatus;

/**
 * The reconciliation job hits each driver's `status()` method and stores
 * the returned StatusResult on the PaymentTransaction row. Drivers must
 * never return PENDING when the upstream gateway has actually settled —
 * map "completed"/"paid"/"captured"/"AUTHORIZED" to PaymentStatus::CAPTURED.
 */
final class StatusResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly PaymentStatus $status,
        public readonly string $reference,
        public readonly ?string $gatewayReference = null,
        public readonly ?string $message = null,
        public readonly array $raw = [],
    ) {}
}
