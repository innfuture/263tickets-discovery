<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SandboxState;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SandboxTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'sandbox_merchant_id',
        'emulate',
        'method',
        'state',
        'reason_code',
        'scenario',
        'amount_minor',
        'amount_captured_minor',
        'amount_refunded_minor',
        'currency',
        'reference',
        'idempotency_key',
        'acquirer_reference',
        'provider_reference',
        'external_id',
        'instrument_brand',
        'instrument_last4',
        'instrument_country',
        'instrument_meta',
        'customer_email',
        'customer_msisdn',
        'customer_name',
        'customer_ip',
        'return_url',
        'result_url',
        'redirect_url',
        'request_body',
        'response_body',
        'metadata',
        'authorized_at',
        'captured_at',
        'voided_at',
        'refunded_at',
        'failed_at',
        'settlement_at',
        'expires_at',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'amount_captured_minor' => 'integer',
        'amount_refunded_minor' => 'integer',
        'instrument_meta' => 'array',
        'request_body' => 'array',
        'response_body' => 'array',
        'metadata' => 'array',
        'authorized_at' => 'datetime',
        'captured_at' => 'datetime',
        'voided_at' => 'datetime',
        'refunded_at' => 'datetime',
        'failed_at' => 'datetime',
        'settlement_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $row) {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(SandboxMerchant::class, 'sandbox_merchant_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(SandboxRefund::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(SandboxDispute::class);
    }

    public function stateLog(): HasMany
    {
        return $this->hasMany(SandboxStateLog::class)->orderBy('occurred_at');
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(SandboxWebhookOutbox::class);
    }

    protected function stateEnum(): Attribute
    {
        return Attribute::make(
            get: fn () => SandboxState::tryFrom((string) $this->state) ?? SandboxState::INITIATED,
        );
    }
}
