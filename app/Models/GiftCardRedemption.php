<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-action ledger entry against a gift card. Append-only — voids
 * are stored as a negative-amount row, never by deleting / mutating
 * a prior row, so audit history is preserved.
 */
class GiftCardRedemption extends Model
{
    use HasFactory;

    public const ACTION_REDEEMED = 'redeemed';

    public const ACTION_VOIDED = 'voided';

    protected $fillable = [
        'gift_card_id', 'checkout_session_id', 'order_id',
        'amount_cents', 'currency', 'action',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
    ];

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
