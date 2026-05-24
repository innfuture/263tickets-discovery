<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A node in the physical-ticket distribution network. May be a Parent
 * (receives bulk dispatches directly from the organization) or a
 * Child (receives transfers from its parent distributor).
 *
 * Lifecycle: pending → active → suspended → terminated.
 * Custody movements are recorded in TicketCustodyLedger; this model
 * stores only the actor's profile, not the inventory itself.
 */
class Distributor extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_TERMINATED = 'terminated';

    public const TYPE_PARENT = 'parent';
    public const TYPE_CHILD = 'child';

    protected $fillable = [
        'uuid', 'organization_id', 'parent_distributor_id',
        'name', 'slug', 'status', 'type',
        'logo_path', 'brand_color',
        'contact_name', 'contact_email', 'contact_phone',
        'geofence_polygon', 'geofence_radius_meters',
        'centroid_lat', 'centroid_lng',
        'commission_model_id', 'float_deposit_cents', 'float_currency',
        'trust_score', 'max_inventory_face_value_cents',
        'metadata',
    ];

    protected $casts = [
        'geofence_polygon' => 'array',
        'metadata' => 'array',
        'centroid_lat' => 'float',
        'centroid_lng' => 'float',
        'trust_score' => 'integer',
        'float_deposit_cents' => 'integer',
        'max_inventory_face_value_cents' => 'integer',
        'geofence_radius_meters' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $row): void {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
            // Auto-set type from parent linkage; explicit override is
            // possible but the default is correct 99% of the time.
            if (empty($row->type)) {
                $row->type = $row->parent_distributor_id
                    ? self::TYPE_CHILD
                    : self::TYPE_PARENT;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Distributor, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_distributor_id');
    }

    /** @return HasMany<Distributor, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_distributor_id');
    }

    /** @return HasMany<DistributorUser, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(DistributorUser::class);
    }

    /** @return HasMany<DistributorDevice, $this> */
    public function devices(): HasMany
    {
        return $this->hasMany(DistributorDevice::class);
    }

    /** @return BelongsTo<CommissionModel, $this> */
    public function commissionModel(): BelongsTo
    {
        return $this->belongsTo(CommissionModel::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isParent(): bool
    {
        return $this->type === self::TYPE_PARENT;
    }
}
