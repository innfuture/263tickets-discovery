<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Short-lived single-use code that exchanges for a paired device
 * token. Mirrors the existing ScannerPairingCode flow.
 */
class DistributorPairingCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'distributor_id', 'code_hash', 'code_prefix', 'label',
        'expires_at', 'redeemed_at', 'redeemed_by_device_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'redeemed_at' => 'datetime',
    ];

    protected $hidden = [
        'code_hash',
    ];

    /** @return BelongsTo<Distributor, $this> */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    public function isRedeemable(): bool
    {
        return $this->redeemed_at === null
            && $this->expires_at->isFuture();
    }
}
