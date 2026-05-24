<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Point-of-sale terminal paired to a Distributor. Generates a P-256
 * keypair at pairing time; the public key is stored on this row and
 * used to verify the device signature on every sale / transfer / void
 * event the terminal submits. The private key never leaves the
 * device's secure storage.
 *
 * Status:
 *   active   — paired and trusted; can record sales.
 *   lost     — distributor reported the device missing. Sales rejected.
 *   retired  — device decommissioned; kept for ledger lookups.
 */
class DistributorDevice extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_LOST = 'lost';
    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'uuid', 'distributor_id', 'label',
        'pairing_token_hash', 'paired_at',
        'attestation_pubkey', 'platform', 'app_version',
        'last_seen_at', 'last_seen_ip',
        'status', 'capabilities',
    ];

    protected $casts = [
        'capabilities' => 'array',
        'paired_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    protected $hidden = [
        'pairing_token_hash',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $row): void {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Distributor, $this> */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->paired_at !== null;
    }
}
