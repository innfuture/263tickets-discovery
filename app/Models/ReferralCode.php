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
 * Sharable referral code owned by an email (typically a past buyer).
 * On every successful referred order, ReferralService mints a gift
 * card for the owner — using the existing gift_cards infrastructure
 * rather than introducing a parallel credit system.
 */
class ReferralCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid', 'organisation_id', 'code', 'owner_email', 'owner_name',
        'reward_cents', 'reward_currency', 'max_rewards',
        'rewards_count', 'expires_at', 'is_active',
    ];

    protected $casts = [
        'reward_cents' => 'integer',
        'max_rewards' => 'integer',
        'rewards_count' => 'integer',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $row) {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
            if (empty($row->code)) {
                $row->code = static::generateCode();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function credits(): HasMany
    {
        return $this->hasMany(ReferralCredit::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organisation_id', 'uuid');
    }

    public function isUsable(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->expires_at instanceof Carbon && $this->expires_at->isPast()) {
            return false;
        }
        if ($this->max_rewards !== null && $this->rewards_count >= $this->max_rewards) {
            return false;
        }

        return true;
    }

    public static function generateCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        return collect(range(1, 8))->map(fn () => $chars[random_int(0, 31)])->implode('');
    }
}
