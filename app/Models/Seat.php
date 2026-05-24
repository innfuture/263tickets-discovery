<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * One bookable seat. `status` is the canonical view of state for the
 * dashboard / public map; it's mirrored from seat_holds + offline
 * tickets by `SeatedReservation::recomputeStatus()` whenever the
 * lifecycle changes.
 *
 * `attributes` JSON carries traits like accessible, restricted_view,
 * companion_seat — used by filters / accessibility UIs.
 */
class Seat extends Model
{
    use HasFactory;

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_HELD = 'held';

    public const STATUS_SOLD = 'sold';

    public const STATUS_BLOCKED = 'blocked';

    protected $fillable = [
        'uuid', 'seat_map_id', 'ticket_category_id', 'zone_code',
        'row_label', 'seat_label', 'x', 'y', 'status', 'attributes',
    ];

    protected $casts = [
        'attributes' => 'array',
        'x' => 'decimal:2',
        'y' => 'decimal:2',
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

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function map(): BelongsTo
    {
        return $this->belongsTo(SeatMap::class, 'seat_map_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    public function activeHold(): HasOne
    {
        return $this->hasOne(SeatHold::class);
    }
}
