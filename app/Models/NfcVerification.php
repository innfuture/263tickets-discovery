<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Audit row per NFC tap. The `payload_hash` unique constraint is
 * what makes replay attacks (operator-side or attacker-side) safe:
 * the same encrypted blob can only be redeemed once, even if a
 * device-side fault re-tries.
 */
class NfcVerification extends Model
{
    use HasFactory;

    public const PROVIDER_APPLE_VAS = 'apple_vas';

    public const PROVIDER_GOOGLE_SMART_TAP = 'google_smart_tap';

    public const PROVIDER_STUB = 'stub';

    protected $fillable = [
        'uuid', 'offline_ticket_id', 'provider', 'payload_hash',
        'scanner_device_id', 'scan_event_id', 'verdict',
        'reason_code', 'metadata', 'verified_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'verified_at' => 'datetime',
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

    public function offlineTicket(): BelongsTo
    {
        return $this->belongsTo(OfflineTicket::class);
    }
}
