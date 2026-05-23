<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Saved payment instruments for COF (card-on-file) / off-session
 * charging. The `token` is what the host app stores and replays;
 * the underlying digits + brand + fingerprint are kept here.
 */
class SandboxPaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'sandbox_merchant_id',
        'token',
        'kind',
        'brand',
        'last4',
        'exp_month',
        'exp_year',
        'country',
        'fingerprint',
        'network_transaction_id',
        'customer_email',
        'metadata',
        'last_used_at',
        'revoked_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $row) {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::ulid();
            }
            if (empty($row->token)) {
                $row->token = 'pm_sbx_'.bin2hex(random_bytes(16));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(SandboxMerchant::class, 'sandbox_merchant_id');
    }
}
