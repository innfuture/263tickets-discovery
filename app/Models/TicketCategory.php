<?php

namespace App\Models;

use App\Enums\AdmissionType;
use App\Enums\PassType;
use App\Enums\TicketGenerationStatus;
use App\Enums\TicketSaleStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'event_id',
    'organisation_id',
    'created_by_user_id',
    'name',
    'description',
    'image_path',
    'admission_type',
    'pass_type',
    'offline_quantity',
    'online_quantity',
    'base_price',
    'base_currency',
    'sale_status',
    'sales_start_at',
    'sales_end_at',
    'is_visible',
    'min_per_order',
    'max_per_order',
    'sort_order',
    'generation_status',
    'generation_progress',
    'scanned_count',
    'requestor_ip',
])]
class TicketCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (TicketCategory $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'admission_type' => AdmissionType::class,
            'pass_type' => PassType::class,
            'sale_status' => TicketSaleStatus::class,
            'generation_status' => TicketGenerationStatus::class,
            'offline_quantity' => 'integer',
            'online_quantity' => 'integer',
            'base_price' => 'decimal:2',
            'is_visible' => 'boolean',
            'min_per_order' => 'integer',
            'max_per_order' => 'integer',
            'sort_order' => 'integer',
            'generation_progress' => 'integer',
            'scanned_count' => 'integer',
            'sales_start_at' => 'datetime',
            'sales_end_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<OfflineTicket, $this> */
    public function offlineTickets(): HasMany
    {
        return $this->hasMany(OfflineTicket::class);
    }

    /** @return HasMany<TicketCurrencyPrice, $this> */
    public function currencyPrices(): HasMany
    {
        return $this->hasMany(TicketCurrencyPrice::class);
    }

    /** @return HasMany<TicketDiscount, $this> */
    public function discounts(): HasMany
    {
        return $this->hasMany(TicketDiscount::class);
    }

    /** @return HasMany<TicketPromoCode, $this> */
    public function promoCodes(): HasMany
    {
        return $this->hasMany(TicketPromoCode::class);
    }

    public function getOfflineGeneratedCountAttribute(): int
    {
        return $this->offlineTickets()->whereNull('deleted_at')->count();
    }
}
