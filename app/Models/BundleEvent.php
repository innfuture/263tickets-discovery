<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot describing one event included in a bundle, plus which tier
 * the bundle holder is entitled to. Null `ticket_category_id` means
 * the organizer issues from whichever tier is most appropriate at
 * fulfilment time (default: first visible tier).
 */
class BundleEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_bundle_id', 'event_id', 'ticket_category_id',
        'quantity', 'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'sort_order' => 'integer',
    ];

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(EventBundle::class, 'event_bundle_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }
}
