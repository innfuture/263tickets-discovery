<?php

declare(strict_types=1);

use App\Models\CommissionModel;
use App\Models\Distributor;
use App\Services\Distribution\Commission\Rules\ChildPassthroughRule;
use App\Services\Distribution\Commission\Rules\FlatPercentRule;
use App\Services\Distribution\Commission\Rules\TieredVolumeRule;
use App\Services\Distribution\Commission\SettlementContext;

function makeCommissionContext(int $sold = 100, int $grossCents = 100000, string $currency = 'USD', array $children = []): SettlementContext
{
    $d = new Distributor();
    $d->forceFill([
        'name' => 't', 'slug' => 't', 'organization_id' => 1,
        'status' => Distributor::STATUS_ACTIVE, 'type' => Distributor::TYPE_PARENT,
    ]);

    return new SettlementContext(
        distributor: $d,
        eventId: 1,
        soldCount: $sold,
        grossRevenueCents: $grossCents,
        currency: $currency,
        childBreakdown: $children,
    );
}

function makeCommissionRuleModel(string $type, array $config): CommissionModel
{
    $m = new CommissionModel();
    $m->forceFill([
        'organization_id' => 1, 'name' => 't',
        'rule_type' => $type, 'rule_config' => $config,
    ]);

    return $m;
}

it('flat percent splits gross by configured percent', function () {
    $rule = new FlatPercentRule();
    $model = makeCommissionRuleModel(CommissionModel::RULE_FLAT_PERCENT, ['percent' => 8.0]);
    $split = $rule->calculate($model, makeCommissionContext(grossCents: 100000));

    expect($split->distributorCents)->toBe(8000)
        ->and($split->platformCents)->toBe(92000)
        ->and($split->currency)->toBe('USD');
});

it('flat percent with 0% gives everything to platform', function () {
    $rule = new FlatPercentRule();
    $model = makeCommissionRuleModel(CommissionModel::RULE_FLAT_PERCENT, ['percent' => 0.0]);
    $split = $rule->calculate($model, makeCommissionContext(grossCents: 50000));

    expect($split->distributorCents)->toBe(0)
        ->and($split->platformCents)->toBe(50000);
});

it('tiered volume selects the highest matching tier', function () {
    $rule = new TieredVolumeRule();
    $model = makeCommissionRuleModel(CommissionModel::RULE_TIERED_VOLUME, [
        'tiers' => [
            ['min_sold' => 0, 'percent' => 5.0],
            ['min_sold' => 1000, 'percent' => 7.0],
            ['min_sold' => 5000, 'percent' => 9.0],
        ],
    ]);

    expect($rule->calculate($model, makeCommissionContext(sold: 500, grossCents: 100000))->distributorCents)->toBe(5000)
        ->and($rule->calculate($model, makeCommissionContext(sold: 2500, grossCents: 100000))->distributorCents)->toBe(7000)
        ->and($rule->calculate($model, makeCommissionContext(sold: 6000, grossCents: 100000))->distributorCents)->toBe(9000);
});

it('tiered volume falls to 0 when no tier matches', function () {
    $rule = new TieredVolumeRule();
    $model = makeCommissionRuleModel(CommissionModel::RULE_TIERED_VOLUME, [
        'tiers' => [['min_sold' => 10000, 'percent' => 9.0]],
    ]);
    $split = $rule->calculate($model, makeCommissionContext(sold: 100, grossCents: 100000));

    expect($split->distributorCents)->toBe(0);
});

it('child passthrough passes each child its share and keeps parent margin', function () {
    $rule = new ChildPassthroughRule();
    $model = makeCommissionRuleModel(CommissionModel::RULE_CHILD_PASSTHROUGH, [
        'parent_margin_percent' => 2.0,
        'child_percent' => 6.0,
    ]);

    $context = makeCommissionContext(
        sold: 200,
        grossCents: 200000,
        children: [
            ['distributor_id' => 10, 'sold_count' => 120, 'gross_cents' => 120000],
            ['distributor_id' => 11, 'sold_count' => 80, 'gross_cents' => 80000],
        ],
    );

    $split = $rule->calculate($model, $context);

    // Parent: 2% of 200000 = 4000.
    expect($split->distributorCents)->toBe(4000);
    // Children: 6% of 120000 = 7200; 6% of 80000 = 4800.
    expect($split->childPassthroughs)->toHaveCount(2);
    expect($split->childPassthroughs[0]['distributor_cents'])->toBe(7200);
    expect($split->childPassthroughs[1]['distributor_cents'])->toBe(4800);
    // Platform: 200000 - 4000 - 7200 - 4800 = 184000.
    expect($split->platformCents)->toBe(184000);
});
