<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per credited referred order. The unique (referral_code_id,
 * order_id) constraint is what makes ReferralService::credit() safely
 * idempotent — a redelivered webhook can't double-pay the referrer.
 */
class ReferralCredit extends Model
{
    use HasFactory;

    protected $fillable = [
        'referral_code_id', 'order_id', 'gift_card_id',
        'amount_cents', 'currency',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
    ];

    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }
}
