<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WaitlistStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Storefront waitlist row — a buyer's interest in an event (or a
 * specific tier) that is currently sold out. The WaitlistManager
 * walks pending entries when inventory frees up.
 */
class WaitlistEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'event_id',
        'ticket_category_id',
        'email',
        'name',
        'phone',
        'quantity_requested',
        'status',
        'notified_at',
        'expires_at',
        'converted_order_id',
    ];

    protected $casts = [
        'status' => WaitlistStatus::class,
        'quantity_requested' => 'integer',
        'notified_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $entry) {
            if (empty($entry->uuid)) {
                $entry->uuid = (string) Str::uuid();
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }
}
