<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Bill-of-lading record for a batch of physical tickets moving from
 * one custody node to another. Carries the Merkle root over the
 * dispatched ticket UUIDs so subset transfers can be proven without
 * re-shipping the full manifest.
 */
class TicketDispatch extends Model
{
    use HasFactory;

    public const STATUS_ISSUED = 'issued';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_DISPUTED = 'disputed';
    public const STATUS_CANCELLED = 'cancelled';

    public const FROM_ORGANIZATION = 'organization';
    public const FROM_DISTRIBUTOR = 'distributor';

    protected $fillable = [
        'uuid', 'from_actor_type', 'from_actor_id', 'to_distributor_id',
        'event_id', 'ticket_count', 'merkle_root',
        'status', 'issued_at', 'dispatched_at', 'received_at', 'disputed_at',
        'tamper_evidence_seal_id', 'tamper_evidence_photo_path',
        'tamper_evidence_photo_hash',
        'manifest_signature', 'signing_key_id', 'notes', 'metadata',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'received_at' => 'datetime',
        'disputed_at' => 'datetime',
        'metadata' => 'array',
        'ticket_count' => 'integer',
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

    /** @return HasMany<TicketDispatchItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(TicketDispatchItem::class, 'dispatch_id');
    }

    /** @return BelongsTo<Distributor, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'to_distributor_id');
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function isFromOrganization(): bool
    {
        return $this->from_actor_type === self::FROM_ORGANIZATION;
    }

    public function isReceivable(): bool
    {
        return in_array($this->status, [self::STATUS_ISSUED, self::STATUS_IN_TRANSIT], true);
    }
}
