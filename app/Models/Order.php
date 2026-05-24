<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Buyer-side order envelope. Lines up with the `orders` table created
 * in the platform migration. Money columns are stored in cents/minor
 * units; helpers expose major-unit formatted values for UI.
 *
 * `organisation_id` is a UUID matching the rest of the platform
 * schema (Organization carries both an autoincrement `id` and a
 * `uuid`). The relationship resolves by the uuid column.
 */
class Order extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'reference',
        'organisation_id',
        'event_id',
        'checkout_session_id',
        'buyer_name',
        'buyer_email',
        'buyer_phone',
        'buyer_country_code',
        'buyer_locale',
        'status',
        'subtotal_cents',
        'discount_cents',
        'tax_cents',
        'fee_cents',
        'total_cents',
        'currency',
        'payment_method',
        'payment_reference',
        'promo_code_used',
        'ip_address',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'metadata',
        'placed_at',
        'fulfilled_at',
    ];

    protected $casts = [
        'subtotal_cents' => 'integer',
        'discount_cents' => 'integer',
        'tax_cents' => 'integer',
        'fee_cents' => 'integer',
        'total_cents' => 'integer',
        'metadata' => 'array',
        'placed_at' => 'datetime',
        'fulfilled_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $order) {
            if (empty($order->uuid)) {
                $order->uuid = (string) Str::uuid();
            }
            if (empty($order->reference)) {
                $order->reference = self::generateReference();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Organisations are keyed on uuid in this schema. The default FK
     * inference would look at `organization_id`; spell out both
     * key columns explicitly.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organisation_id', 'uuid');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isRefundable(): bool
    {
        return in_array($this->status, ['paid', 'partially_refunded'], true);
    }

    public function totalFormatted(): string
    {
        return number_format($this->total_cents / 100, 2);
    }

    /**
     * ORD-AB12-3CD4 style. Public-safe (no PK leakage), short enough
     * for support to read over the phone.
     */
    protected static function generateReference(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // omit I/O/0/1 ambiguity

        return 'ORD-'.collect(range(1, 4))->map(fn () => $chars[random_int(0, 31)])->implode('')
            .'-'.collect(range(1, 4))->map(fn () => $chars[random_int(0, 31)])->implode('');
    }
}
