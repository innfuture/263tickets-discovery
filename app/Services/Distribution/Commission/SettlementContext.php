<?php

declare(strict_types=1);

namespace App\Services\Distribution\Commission;

use App\Models\Distributor;

final class SettlementContext
{
    /**
     * @param  array<int, array{distributor_id: int, sold_count: int, gross_cents: int}>  $childBreakdown
     */
    public function __construct(
        public readonly Distributor $distributor,
        public readonly ?int $eventId,
        public readonly int $soldCount,
        public readonly int $grossRevenueCents,
        public readonly string $currency,
        public readonly array $childBreakdown = [],
    ) {}
}
