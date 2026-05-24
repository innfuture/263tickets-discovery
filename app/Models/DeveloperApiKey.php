<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Public-API bearer key. `tier` is pinned at issuance so a developer
 * downgrade automatically revokes elevated quotas / scopes (operator
 * mints a fresh key after a tier change).
 *
 * `key_prefix` is the first 12 chars of the plaintext (e.g. `dvk_pub_FA…`)
 * shown in dashboards for identification without revealing the secret.
 */
class DeveloperApiKey extends Model
{
    use HasFactory;

    public const KEY_PREFIX = 'dvk_';

    public const SCOPE_EVENTS_READ = 'events.read';

    public const SCOPE_EVENTS_LIST = 'events.list';

    public const SCOPE_ORDERS_AGGREGATE = 'orders.aggregate.read';

    public const SCOPE_ANALYTICS_READ = 'analytics.read';

    public const SCOPE_WEBHOOKS_SUBSCRIBE = 'webhooks.subscribe';

    public const ALL_SCOPES = [
        self::SCOPE_EVENTS_READ,
        self::SCOPE_EVENTS_LIST,
        self::SCOPE_ORDERS_AGGREGATE,
        self::SCOPE_ANALYTICS_READ,
        self::SCOPE_WEBHOOKS_SUBSCRIBE,
    ];

    protected $fillable = [
        'uuid', 'developer_account_id', 'label', 'key_hash',
        'key_prefix', 'tier', 'scopes', 'monthly_quota',
        'quota_used_this_month', 'quota_reset_at',
        'last_used_at', 'last_used_ip', 'expires_at', 'revoked_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'monthly_quota' => 'integer',
        'quota_used_this_month' => 'integer',
        'quota_reset_at' => 'datetime',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
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

    public function developerAccount(): BelongsTo
    {
        return $this->belongsTo(DeveloperAccount::class);
    }

    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function hasScope(string $scope): bool
    {
        $scopes = (array) ($this->scopes ?? []);

        return in_array($scope, $scopes, true) || in_array('*', $scopes, true);
    }

    public function quotaExceeded(): bool
    {
        if ($this->monthly_quota === null) {
            return false;
        }
        $this->rolloverIfNeeded();

        return $this->quota_used_this_month >= $this->monthly_quota;
    }

    /**
     * If the calendar month has flipped, reset the rolling counter.
     * Done lazily on access — saves a daily cron just for this.
     */
    public function rolloverIfNeeded(): void
    {
        $reset = $this->quota_reset_at;
        if ($reset === null || $reset->isPast()) {
            $this->forceFill([
                'quota_used_this_month' => 0,
                'quota_reset_at' => Carbon::now()->startOfMonth()->addMonthNoOverflow(),
            ])->save();
        }
    }
}
