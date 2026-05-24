<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeveloperSubscription extends Model
{
    use HasFactory;

    public const TIER_FREE = 'free';

    public const TIER_BASIC = 'basic';

    public const TIER_ENTERPRISE = 'enterprise';

    public const TIER_PREMIUM = 'premium';

    public const ALL_TIERS = [
        self::TIER_FREE,
        self::TIER_BASIC,
        self::TIER_ENTERPRISE,
        self::TIER_PREMIUM,
    ];

    protected $fillable = [
        'developer_account_id', 'tier', 'billing_status',
        'starts_at', 'renews_at', 'expires_at', 'metadata',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'renews_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function developerAccount(): BelongsTo
    {
        return $this->belongsTo(DeveloperAccount::class);
    }
}
