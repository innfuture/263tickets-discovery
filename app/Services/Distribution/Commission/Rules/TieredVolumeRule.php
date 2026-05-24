<?php

declare(strict_types=1);

namespace App\Services\Distribution\Commission\Rules;

use App\Models\CommissionModel;
use App\Services\Distribution\Commission\CommissionRule;
use App\Services\Distribution\Commission\CommissionSplit;
use App\Services\Distribution\Commission\SettlementContext;

/**
 * Stepped commission rate based on sold ticket count.
 *
 * Config: {
 *   "tiers": [
 *     { "min_sold": 0,    "percent": 5.0 },
 *     { "min_sold": 1000, "percent": 7.0 },
 *     { "min_sold": 5000, "percent": 9.0 }
 *   ]
 * }
 *
 * The selected percent is applied to the FULL gross (not just the
 * marginal slab), matching how most ticketing-distribution deals
 * actually pay out.
 */
class TieredVolumeRule implements CommissionRule
{
    public static function ruleType(): string
    {
        return CommissionModel::RULE_TIERED_VOLUME;
    }

    public function calculate(CommissionModel $model, SettlementContext $context): CommissionSplit
    {
        $tiers = (array) ($model->rule_config['tiers'] ?? []);
        // Sort descending by min_sold so the first match wins.
        usort($tiers, fn (array $a, array $b): int => (int) $b['min_sold'] - (int) $a['min_sold']);

        $percent = 0.0;
        foreach ($tiers as $tier) {
            if ($context->soldCount >= (int) ($tier['min_sold'] ?? 0)) {
                $percent = (float) ($tier['percent'] ?? 0.0);
                break;
            }
        }

        $distributorCents = (int) round($context->grossRevenueCents * $percent / 100.0);

        return new CommissionSplit(
            distributorCents: $distributorCents,
            platformCents: max(0, $context->grossRevenueCents - $distributorCents),
            currency: $context->currency,
            ruleType: self::ruleType(),
            notes: "Tier matched at {$percent}% for sold_count={$context->soldCount}.",
        );
    }
}
