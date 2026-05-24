<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StakeholderType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A non-organizer commercial entity that plugs into events. Sponsors,
 * media partners, food vendors, service providers, etc. — each
 * `type` selects a workflow strategy class.
 *
 * Distinct from `User` (organizer staff with WorkOS auth) and
 * `Buyer` (ticket purchasers with magic-link). Stakeholders also
 * authenticate by magic-link; identity is their business email.
 */
class Stakeholder extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'uuid', 'type', 'email', 'name', 'company', 'phone',
        'country_code', 'status', 'email_verified_at', 'verified_at',
        'suspended_at', 'suspended_reason', 'last_login_at',
        'last_login_ip', 'metadata',
    ];

    protected $casts = [
        'type' => StakeholderType::class,
        'metadata' => 'array',
        'email_verified_at' => 'datetime',
        'verified_at' => 'datetime',
        'suspended_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $row) {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
            $row->email = strtolower((string) $row->email);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function profile(): HasOne
    {
        return $this->hasOne(StakeholderProfile::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(StakeholderService::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(StakeholderInvitation::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(StakeholderApplication::class);
    }

    public function engagements(): HasMany
    {
        return $this->hasMany(EventStakeholderEngagement::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StakeholderDocument::class);
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED && $this->verified_at !== null;
    }

    public function canAcceptNewWork(): bool
    {
        return $this->isVerified()
            && optional($this->profile)->accepting_invitations !== false;
    }
}
