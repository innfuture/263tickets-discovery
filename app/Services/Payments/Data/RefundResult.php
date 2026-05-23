<?php

declare(strict_types=1);

namespace App\Services\Payments\Data;

use App\Enums\PaymentStatus;

final class RefundResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly PaymentStatus $status,
        public readonly string $reference,
        public readonly ?string $gatewayReference = null,
        public readonly array $raw = [],
    ) {}
}
