<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Buyer-to-buyer ticket handoff. Anti-scalper alternative: optional
 * sale_price_cents, but the original buyer pays the new ticket
 * issuance cost (or we collect a transfer fee — TBD policy).
 *
 * Lifecycle: offered → claimed (recipient opened the link) →
 * completed (new ticket issued, old voided).
 */
class TicketTransfer extends Model
{
    use HasFactory;

    public const STATUS_OFFERED = 'offered';

    public const STATUS_CLAIMED = 'claimed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'uuid', 'offline_ticket_id', 'source_order_item_id',
        'new_order_item_id', 'from_email', 'to_email', 'to_name',
        'message', 'status', 'claim_token', 'sale_price_cents',
        'currency', 'expires_at', 'claimed_at', 'completed_at',
    ];

    protected $casts = [
        'sale_price_cents' => 'integer',
        'expires_at' => 'datetime',
        'claimed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $row) {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
            if (empty($row->claim_token)) {
                $row->claim_token = Str::random(48);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function offlineTicket(): BelongsTo
    {
        return $this->belongsTo(OfflineTicket::class);
    }

    public function sourceOrderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'source_order_item_id');
    }
}
