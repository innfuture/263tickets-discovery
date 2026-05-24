<?php

declare(strict_types=1);

namespace App\Services\Distribution\Commission;

use App\Models\CommissionModel;
use App\Models\Distributor;
use App\Services\Distribution\Commission\Rules\ChildPassthroughRule;
use App\Services\Distribution\Commission\Rules\FlatPercentRule;
use App\Services\Distribution\Commission\Rules\TieredVolumeRule;
use RuntimeException;

/**
 * Resolves the active commission model for a distributor, then
 * dispatches to the rule implementation matching its rule_type.
 *
 * Resolution order: distributor.commission_model_id →
 * org_default (is_default=true) → throw (no rule configured).
 */
class CommissionRuleResolver
{
    /** @var array<string, class-string<CommissionRule>> */
    protected array $rules = [
        CommissionModel::RULE_FLAT_PERCENT => FlatPercentRule::class,
        CommissionModel::RULE_TIERED_VOLUME => TieredVolumeRule::class,
        CommissionModel::RULE_CHILD_PASSTHROUGH => ChildPassthroughRule::class,
    ];

    public function resolveFor(Distributor $distributor): CommissionModel
    {
        if ($distributor->commission_model_id) {
            $model = CommissionModel::query()->find($distributor->commission_model_id);
            if ($model) {
                return $model;
            }
        }

        $default = CommissionModel::query()
            ->where('organization_id', $distributor->organization_id)
            ->where('is_default', true)
            ->first();

        if (! $default) {
            throw new RuntimeException("No commission model configured for distributor {$distributor->uuid} (org {$distributor->organization_id}).");
        }

        return $default;
    }

    public function calculate(Distributor $distributor, SettlementContext $context): CommissionSplit
    {
        $model = $this->resolveFor($distributor);
        $ruleClass = $this->rules[$model->rule_type] ?? null;
        if ($ruleClass === null) {
            throw new RuntimeException("No implementation registered for rule_type {$model->rule_type}.");
        }

        /** @var CommissionRule $rule */
        $rule = new $ruleClass();

        return $rule->calculate($model, $context);
    }
}
