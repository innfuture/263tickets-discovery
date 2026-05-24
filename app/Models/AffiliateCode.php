<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Affiliate code carrying a per-order commission for the bearer.
 * Distinct from the existing ReferralCode model (which is a buyer
 * gift-credit mechanism); this one pays an external partner a cut
 * of each sale they refer.
 *
 * commission_bps and commission_flat_cents_per_ticket can be set
 * together; the AffiliateAttributor sums both at sale time and
 * caps at order subtotal.
 */
class AffiliateCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid', 'organization_id', 'event_id', 'code', 'label',
        'affiliate_name', 'affiliate_email',
        'commission_bps', 'commission_flat_cents_per_ticket', 'currency',
        'max_uses', 'uses_count', 'starts_at', 'expires_at', 'is_active',
        'metadata',
    ];

    protected $casts = [
        'commission_bps' => 'integer',
        'commission_flat_cents_per_ticket' => 'integer',
        'max_uses' => 'integer',
        'uses_count' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $row): void {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
            $row->code = strtoupper((string) $row->code);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return HasMany<AffiliateAttribution, $this> */
    public function attributions(): HasMany
    {
        return $this->hasMany(AffiliateAttribution::class);
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * True if the code is currently redeemable: active, in its
     * window, and below its max-uses cap if set.
     */
    public function isRedeemable(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        if ($this->max_uses !== null && $this->uses_count >= $this->max_uses) {
            return false;
        }

        return true;
    }
}
