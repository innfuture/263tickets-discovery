<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Authenticated buyer account. Distinct from `users` (which are
 * organizer-side WorkOS-backed accounts). Buyers authenticate via
 * email magic-link (no password) — the simplest path that doesn't
 * require buyers to remember a password for an annual purchase.
 *
 * Orders that pre-existed the buyer's signup are linked retroactively
 * by email match at registration time.
 */
class Buyer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'email', 'name', 'phone', 'country_code', 'locale',
        'marketing_opt_in', 'notifications_email', 'notifications_sms',
        'email_verified_at', 'last_login_at', 'last_login_ip',
        'preferences',
    ];

    protected $casts = [
        'preferences' => 'array',
        'marketing_opt_in' => 'boolean',
        'notifications_email' => 'boolean',
        'notifications_sms' => 'boolean',
        'email_verified_at' => 'datetime',
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

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(BuyerFavorite::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(BuyerNotification::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(BuyerSession::class);
    }
}
