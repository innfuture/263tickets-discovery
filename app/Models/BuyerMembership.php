<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Annual "Friends of the Theatre" style buyer membership. Distinct
 * from `Membership` (team pivot — pre-existing). Periods are tracked
 * in `membership_periods`; this row holds the rolling
 * `starts_at` / `ends_at` that consumers query.
 *
 * Backed by the `memberships` table — kept the table name short, but
 * the model is namespaced to avoid the pivot collision.
 */
class BuyerMembership extends Model
{
    use HasFactory;

    protected $table = 'memberships';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid', 'organisation_id', 'plan_name', 'plan_description',
        'price_cents', 'currency', 'period_months', 'benefits',
        'member_email', 'member_name', 'status', 'starts_at', 'ends_at',
        'renewed_at', 'cancelled_at', 'auto_renew',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'period_months' => 'integer',
        'benefits' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'renewed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $row) {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function periods(): HasMany
    {
        return $this->hasMany(BuyerMembershipPeriod::class, 'membership_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organisation_id', 'uuid');
    }

    public function isCurrentlyActive(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        return $this->ends_at instanceof Carbon && $this->ends_at->isFuture();
    }
}
