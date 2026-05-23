<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * One payer-side payment attempt against an organization. Lifecycle:
 *
 *   pending  ──►  captured / authorized   (webhook or reconciler)
 *            ──►  failed / cancelled      (terminal)
 *            ──►  refunded                (after a successful PaymentRefund)
 *
 * The `payable` morph points back at the domain object the payment
 * settled (Order, Ticket purchase, Subscription invoice…).
 */
class PaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'organization_id',
        'user_id',
        'payable_type',
        'payable_id',
        'gateway',
        'reference',
        'gateway_reference',
        'status',
        'amount_minor',
        'currency',
        'description',
        'customer_email',
        'customer_msisdn',
        'customer_name',
        'customer_ip',
        'return_url',
        'result_url',
        'redirect_url',
        'poll_url',
        'instructions',
        'metadata',
        'last_response',
        'reconcile_attempts',
        'last_reconciled_at',
        'settled_at',
        'failed_at',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'metadata' => 'array',
        'last_response' => 'array',
        'reconcile_attempts' => 'integer',
        'last_reconciled_at' => 'datetime',
        'settled_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $row) {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    protected function statusEnum(): Attribute
    {
        return Attribute::make(
            get: fn () => PaymentStatus::tryFrom((string) $this->status) ?? PaymentStatus::PENDING,
        );
    }

    public function markStatus(PaymentStatus $status, ?array $raw = null): void
    {
        $this->status = $status->value;
        if ($raw !== null) {
            $this->last_response = $raw;
        }
        if ($status === PaymentStatus::CAPTURED || $status === PaymentStatus::AUTHORIZED) {
            $this->settled_at = $this->settled_at ?? now();
        }
        if ($status === PaymentStatus::FAILED || $status === PaymentStatus::CANCELLED) {
            $this->failed_at = $this->failed_at ?? now();
        }
        $this->save();
    }
}
