<?php

declare(strict_types=1);

namespace App\Services\Distribution\Commission;

use App\Models\CommissionModel;

/**
 * Strategy contract: given a settlement context (gross revenue,
 * counts, distributor + parent), return the commission split as
 * cents-to-distributor and cents-to-platform.
 *
 * Implementations are pure functions of (config, context) — no DB
 * writes — so settlement runs are deterministic and reproducible.
 */
interface CommissionRule
{
    public static function ruleType(): string;

    public function calculate(CommissionModel $model, SettlementContext $context): CommissionSplit;
}
