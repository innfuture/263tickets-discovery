<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BuyerNotification extends Model
{
    use HasFactory;

    // Standard types — feature codes; per-type rendering happens UI-side.
    public const TYPE_ORDER_CONFIRMED = 'order.confirmed';

    public const TYPE_EVENT_REMINDER = 'event.reminder';

    public const TYPE_EVENT_CHANGED = 'event.changed';

    public const TYPE_WAITLIST_AVAILABLE = 'waitlist.available';

    public const TYPE_TRANSFER_RECEIVED = 'transfer.received';

    public const TYPE_REFUND_PROCESSED = 'refund.processed';

    public const TYPE_MEMBERSHIP_EXPIRING = 'membership.expiring';

    public const TYPE_PROMO = 'promo';

    protected $fillable = [
        'uuid', 'buyer_id', 'type', 'title', 'body', 'data',
        'action_url', 'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
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

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }
}
