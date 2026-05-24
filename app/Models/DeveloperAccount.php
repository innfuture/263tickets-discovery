<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class DeveloperAccount extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_BANNED = 'banned';

    protected $fillable = [
        'uuid', 'email', 'name', 'company', 'country_code',
        'website', 'status', 'metadata',
        'portal_bootstrap_token_hash', 'portal_bootstrap_token_rotated_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'portal_bootstrap_token_rotated_at' => 'datetime',
    ];

    protected $hidden = [
        'portal_bootstrap_token_hash',
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

    public function apiKeys(): HasMany
    {
        return $this->hasMany(DeveloperApiKey::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(DeveloperSubscription::class)
            ->where('billing_status', 'active')
            ->latestOfMany();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(DeveloperSubscription::class);
    }
}
