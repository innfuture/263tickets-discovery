<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * "Buy 4, save 10%" style rule applied automatically during price
 * compute. Scoped to a tier or an entire event. Exactly one of
 * `discount_percent`, `discount_fixed_cents`, or (free_quantity +
 * min_quantity) must be non-null.
 */
class QuantityDiscountRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid', 'organisation_id', 'name', 'ticket_category_id',
        'event_id', 'min_quantity', 'discount_percent',
        'discount_fixed_cents', 'free_quantity',
        'valid_from', 'valid_until', 'is_active',
    ];

    protected $casts = [
        'min_quantity' => 'integer',
        'discount_percent' => 'integer',
        'discount_fixed_cents' => 'integer',
        'free_quantity' => 'integer',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
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

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    public function isCurrentlyValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->valid_from instanceof Carbon && $this->valid_from->isFuture()) {
            return false;
        }
        if ($this->valid_until instanceof Carbon && $this->valid_until->isPast()) {
            return false;
        }

        return true;
    }
}
