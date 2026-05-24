<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ticket UUID inside a dispatch manifest, with its Merkle inclusion
 * proof so subset transfers can be verified against the parent root.
 */
class TicketDispatchItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispatch_id', 'offline_ticket_id', 'ticket_uuid',
        'merkle_leaf_hash', 'merkle_path', 'scrap_reason',
    ];

    protected $casts = [
        'merkle_path' => 'array',
    ];

    /** @return BelongsTo<TicketDispatch, $this> */
    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(TicketDispatch::class, 'dispatch_id');
    }

    /** @return BelongsTo<OfflineTicket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(OfflineTicket::class, 'offline_ticket_id');
    }
}
