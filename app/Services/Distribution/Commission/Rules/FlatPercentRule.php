<?php

declare(strict_types=1);

namespace App\Services\Distribution\Commission\Rules;

use App\Models\CommissionModel;
use App\Services\Distribution\Commission\CommissionRule;
use App\Services\Distribution\Commission\CommissionSplit;
use App\Services\Distribution\Commission\SettlementContext;

/**
 * Flat percentage of gross to the distributor.
 *
 * Config: { "percent": 8.0 }   // 8% to distributor, remainder to platform
 */
class FlatPercentRule implements CommissionRule
{
    public static function ruleType(): string
    {
        return CommissionModel::RULE_FLAT_PERCENT;
    }

    public function calculate(CommissionModel $model, SettlementContext $context): CommissionSplit
    {
        $percent = (float) ($model->rule_config['percent'] ?? 0.0);
        $distributorCents = (int) round($context->grossRevenueCents * $percent / 100.0);
        $platformCents = max(0, $context->grossRevenueCents - $distributorCents);

        return new CommissionSplit(
            distributorCents: $distributorCents,
            platformCents: $platformCents,
            currency: $context->currency,
            ruleType: self::ruleType(),
        );
    }
}
