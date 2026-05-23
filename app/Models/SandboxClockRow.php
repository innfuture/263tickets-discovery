<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-merchant virtual clock state (§15 #3). The SandboxClock service
 * reads/writes this row to translate wall time → virtual time.
 *
 * Two modes:
 *   real     `frozen_at` null, `offset_seconds` 0 — clock = wall time
 *   virtual  `offset_seconds` accumulates; virtual = wall + offset
 *
 * One row per merchant; `sandbox_merchant_id` is uniqued at the schema.
 */
class SandboxClockRow extends Model
{
    use HasFactory;

    protected $table = 'sandbox_clocks';

    protected $fillable = [
        'sandbox_merchant_id',
        'frozen_at',
        'offset_seconds',
        'mode',
    ];

    protected $casts = [
        'frozen_at' => 'datetime',
        'offset_seconds' => 'integer',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(SandboxMerchant::class, 'sandbox_merchant_id');
    }
}
