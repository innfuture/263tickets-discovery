<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Order ↔ one AffiliateCode (unique constraint enforces single
 * attribution per order). commission_cents is frozen at sale time.
 */
class AffiliateAttribution extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCRUED = 'accrued';
    public const STATUS_PAID = 'paid';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'affiliate_code_id', 'order_id',
        'commission_cents', 'currency', 'settlement_status',
        'attributed_at', 'settled_at',
    ];

    protected $casts = [
        'commission_cents' => 'integer',
        'attributed_at' => 'datetime',
        'settled_at' => 'datetime',
    ];

    /** @return BelongsTo<AffiliateCode, $this> */
    public function affiliateCode(): BelongsTo
    {
        return $this->belongsTo(AffiliateCode::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
