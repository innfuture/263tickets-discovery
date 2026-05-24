<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScannerCapability;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A scanner profile is the policy + capabilities + scope for one or
 * many physical scanning devices. Organizers create profiles like
 * "Main entrance — Saturday" or "VIP lane" and then pair as many
 * devices to each as they need.
 *
 * Capabilities are stored as a JSON list drawn from a known vocab
 * (scan|verify|revoke|view_analytics). Mobile apps read /me to learn
 * what they're allowed to do; they should *not* assume capabilities.
 */
class ScannerProfile extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'organisation_id',
        'name',
        'description',
        'status',
        'capabilities',
        'allowed_event_ids',
        'ip_allowlist',
        'max_scans_per_minute',
        'duplicate_window_seconds',
        'webhook_url',
        'webhook_secret',
        'created_by',
    ];

    protected $casts = [
        'capabilities' => 'array',
        'allowed_event_ids' => 'array',
        'ip_allowlist' => 'array',
        'max_scans_per_minute' => 'integer',
        'duplicate_window_seconds' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $row) {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
            if (empty($row->webhook_secret) && ! empty($row->webhook_url)) {
                $row->webhook_secret = 'whsec_scn_'.bin2hex(random_bytes(24));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organisation_id', 'uuid');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(ScannerDevice::class);
    }

    public function pairingCodes(): HasMany
    {
        return $this->hasMany(ScannerPairingCode::class);
    }

    public function scanEvents(): HasMany
    {
        return $this->hasMany(ScanEvent::class);
    }

    /**
     * @param  string|ScannerCapability  $cap
     */
    public function has(string $cap): bool
    {
        return in_array($cap, (array) $this->capabilities, true);
    }

    public function canScanEvent(int $eventId): bool
    {
        $allowed = (array) $this->allowed_event_ids;

        return $allowed === [] || in_array($eventId, $allowed, true);
    }
}
