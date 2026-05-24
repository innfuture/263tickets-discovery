<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Outbox for live wallet-pass updates. Each row corresponds to one
 * update (door change, time shift, void) for one ticket; the
 * DispatchPendingWalletUpdatesJob picks queued rows + fans out the
 * push to Apple PassKit web service + Google Wallet REST.
 */
class WalletPassUpdate extends Model
{
    use HasFactory;

    public const TYPE_DOOR_CHANGE = 'door_change';
    public const TYPE_TIME_SHIFT = 'time_shift';
    public const TYPE_CANCELLATION = 'cancellation';
    public const TYPE_VOIDED = 'voided';
    public const TYPE_GENERAL = 'general';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_DISPATCHED_APPLE = 'dispatched_apple';
    public const STATUS_DISPATCHED_GOOGLE = 'dispatched_google';
    public const STATUS_DISPATCHED_BOTH = 'dispatched_both';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'uuid', 'offline_ticket_id', 'ticket_uuid',
        'update_type', 'payload', 'dispatch_status',
        'queued_at', 'dispatched_at', 'attempts', 'last_error',
    ];

    protected $casts = [
        'payload' => 'array',
        'queued_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'attempts' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $row): void {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<OfflineTicket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(OfflineTicket::class, 'offline_ticket_id');
    }
}
