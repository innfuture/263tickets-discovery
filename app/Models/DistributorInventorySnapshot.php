<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Streaming materialised view of a distributor's per-event inventory.
 * Refreshed on every ledger insert by the InventorySnapshotService.
 * Negative `on_hand_count` is impossible by construction (writes guard
 * with a CHECK) — that condition would mean ledger corruption.
 */
class DistributorInventorySnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'distributor_id', 'event_id',
        'received_count', 'transferred_in_count', 'transferred_out_count',
        'sold_count', 'voided_count', 'recovered_count', 'on_hand_count',
        'gross_revenue_cents', 'currency', 'computed_at',
    ];

    protected $casts = [
        'computed_at' => 'datetime',
        'received_count' => 'integer',
        'transferred_in_count' => 'integer',
        'transferred_out_count' => 'integer',
        'sold_count' => 'integer',
        'voided_count' => 'integer',
        'recovered_count' => 'integer',
        'on_hand_count' => 'integer',
        'gross_revenue_cents' => 'integer',
    ];

    /** @return BelongsTo<Distributor, $this> */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
