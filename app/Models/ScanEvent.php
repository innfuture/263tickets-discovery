<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Append-only log of every scan attempt — successes, denials,
 * lookups, voids. Distinct from `offline_tickets.scan_count` (cheap
 * running tally) — this is the audit trail and the data source for
 * scanner analytics + fraud retro.
 */
class ScanEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'scanner_device_id',
        'scanner_profile_id',
        'event_id',
        'offline_ticket_id',
        'payload',
        'verdict',
        'reason_code',
        'fraud_flags',
        'was_duplicate',
        'was_voided',
        'was_admitted',
        'client_lat',
        'client_lng',
        'client_ip',
        'client_at',
        'latency_ms',
        'device_meta',
        'created_at',
    ];

    protected $casts = [
        'fraud_flags' => 'array',
        'device_meta' => 'array',
        'was_duplicate' => 'boolean',
        'was_voided' => 'boolean',
        'was_admitted' => 'boolean',
        'client_at' => 'datetime',
        'created_at' => 'datetime',
        'client_lat' => 'decimal:7',
        'client_lng' => 'decimal:7',
        'latency_ms' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $row) {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::ulid();
            }
            if (empty($row->created_at)) {
                $row->created_at = now();
            }
        });
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(ScannerDevice::class, 'scanner_device_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ScannerProfile::class, 'scanner_profile_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(OfflineTicket::class, 'offline_ticket_id');
    }
}
