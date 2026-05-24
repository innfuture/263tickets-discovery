<?php

declare(strict_types=1);

namespace App\Services\Distribution\Commission\Rules;

use App\Models\CommissionModel;
use App\Services\Distribution\Commission\CommissionRule;
use App\Services\Distribution\Commission\CommissionSplit;
use App\Services\Distribution\Commission\SettlementContext;

/**
 * Parent distributor passes the child's share through verbatim and
 * keeps a fixed margin on the gross. Useful when a Parent is a
 * wholesale aggregator rather than a sales channel of its own.
 *
 * Config: {
 *   "parent_margin_percent": 2.0,
 *   "child_percent": 6.0
 * }
 */
class ChildPassthroughRule implements CommissionRule
{
    public static function ruleType(): string
    {
        return CommissionModel::RULE_CHILD_PASSTHROUGH;
    }

    public function calculate(CommissionModel $model, SettlementContext $context): CommissionSplit
    {
        $parentMarginPct = (float) ($model->rule_config['parent_margin_percent'] ?? 0.0);
        $childPct = (float) ($model->rule_config['child_percent'] ?? 0.0);

        $parentMarginCents = (int) round($context->grossRevenueCents * $parentMarginPct / 100.0);

        $childPassthroughs = [];
        $childTotal = 0;
        foreach ($context->childBreakdown as $row) {
            $childCents = (int) round(($row['gross_cents'] ?? 0) * $childPct / 100.0);
            $childPassthroughs[] = [
                'distributor_id' => $row['distributor_id'],
                'distributor_cents' => $childCents,
            ];
            $childTotal += $childCents;
        }

        $platformCents = max(0, $context->grossRevenueCents - $parentMarginCents - $childTotal);

        return new CommissionSplit(
            distributorCents: $parentMarginCents,
            platformCents: $platformCents,
            currency: $context->currency,
            ruleType: self::ruleType(),
            childPassthroughs: $childPassthroughs,
            notes: "Parent margin {$parentMarginPct}%, child rate {$childPct}%.",
        );
    }
}
