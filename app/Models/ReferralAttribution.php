<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Order ↔ one ReferralCode (unique constraint enforces single
 * attribution per order). commission_cents is frozen at sale time.
 */
class ReferralAttribution extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCRUED = 'accrued';
    public const STATUS_PAID = 'paid';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'referral_code_id', 'order_id',
        'commission_cents', 'currency', 'settlement_status',
        'attributed_at', 'settled_at',
    ];

    protected $casts = [
        'commission_cents' => 'integer',
        'attributed_at' => 'datetime',
        'settled_at' => 'datetime',
    ];

    /** @return BelongsTo<ReferralCode, $this> */
    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
