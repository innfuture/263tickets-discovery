<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CheckoutSessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Public storefront checkout session — a held cart with a TTL. While
 * `status` is open or paying, every `checkout_session_items` row
 * counts against remaining inventory for its ticket category.
 *
 * `organisation_id` is the platform-wide UUID (matches events and
 * the rest of the schema) so the dashboard can scope queries without
 * joining through events.
 *
 * Money columns are minor units. The Calculator services are the
 * single source of truth for totals; controllers never write them.
 */
class CheckoutSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'organisation_id',
        'event_id',
        'status',
        'buyer_email',
        'buyer_name',
        'buyer_phone',
        'buyer_country_code',
        'buyer_locale',
        'currency',
        'subtotal_cents',
        'discount_cents',
        'tax_cents',
        'fee_cents',
        'total_cents',
        'promo_code_id',
        'promo_code_snapshot',
        'attendee_data',
        'referral_source',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'ip_address',
        'user_agent_hash',
        'payment_transaction_id',
        'order_id',
        'idempotency_key',
        'expires_at',
        'locked_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'status' => CheckoutSessionStatus::class,
        'subtotal_cents' => 'integer',
        'discount_cents' => 'integer',
        'tax_cents' => 'integer',
        'fee_cents' => 'integer',
        'total_cents' => 'integer',
        'attendee_data' => 'array',
        'expires_at' => 'datetime',
        'locked_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $session) {
            if (empty($session->uuid)) {
                $session->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CheckoutSessionItem::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(CheckoutSessionAddon::class);
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(TicketPromoCode::class, 'promo_code_id');
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organisation_id', 'uuid');
    }

    public function isMutable(): bool
    {
        return $this->status instanceof CheckoutSessionStatus
            ? $this->status->isMutable()
            : false;
    }

    public function holdsInventory(): bool
    {
        return $this->status instanceof CheckoutSessionStatus
            ? $this->status->holdsInventory()
            : false;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
