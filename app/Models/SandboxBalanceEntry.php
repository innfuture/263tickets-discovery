<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per credit/debit. The merchant's balance at any virtual-time
 * cutoff is sum(credit) - sum(debit) over entries whose `available_at`
 * is in the past at that cutoff.
 */
class SandboxBalanceEntry extends Model
{
    use HasFactory;

    protected $table = 'sandbox_balance_ledger';

    protected $fillable = [
        'sandbox_merchant_id',
        'sandbox_transaction_id',
        'sandbox_refund_id',
        'sandbox_dispute_id',
        'direction',
        'amount_minor',
        'currency',
        'category',
        'memo',
        'available_at',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'available_at' => 'datetime',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(SandboxMerchant::class, 'sandbox_merchant_id');
    }
}
