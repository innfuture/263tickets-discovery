<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Pluggable commission rule attached to organizations and overridable
 * per-distributor. Resolved by App\Services\Distribution\Commission\
 * CommissionRuleResolver at settlement time.
 */
class CommissionModel extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const RULE_FLAT_PERCENT = 'flat_percent';
    public const RULE_TIERED_VOLUME = 'tiered_volume';
    public const RULE_PER_SKU_RATE = 'per_sku_rate';
    public const RULE_CHILD_PASSTHROUGH = 'child_passthrough_plus_margin';

    protected $fillable = [
        'uuid', 'organization_id', 'name', 'rule_type',
        'rule_config', 'is_default',
    ];

    protected $casts = [
        'rule_config' => 'array',
        'is_default' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $row): void {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
