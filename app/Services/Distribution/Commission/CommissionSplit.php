<?php

declare(strict_types=1);

namespace App\Services\Distribution\Commission;

final class CommissionSplit
{
    /**
     * @param  array<int, array{distributor_id: int, distributor_cents: int}>  $childPassthroughs
     */
    public function __construct(
        public readonly int $distributorCents,
        public readonly int $platformCents,
        public readonly string $currency,
        public readonly string $ruleType,
        public readonly array $childPassthroughs = [],
        public readonly ?string $notes = null,
    ) {}
}
