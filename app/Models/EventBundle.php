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
 * Season pass / multi-event bundle. One purchase issues N OfflineTickets,
 * one per `bundle_events` row. Total capacity caps overall sales; per-
 * event capacity still applies on the underlying TicketCategory rows.
 */
class EventBundle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'organisation_id', 'name', 'description', 'slug',
        'price_cents', 'currency', 'savings_cents', 'total_capacity',
        'sold_count', 'sales_start_at', 'sales_end_at', 'is_visible',
        'image_path',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'savings_cents' => 'integer',
        'total_capacity' => 'integer',
        'sold_count' => 'integer',
        'sales_start_at' => 'datetime',
        'sales_end_at' => 'datetime',
        'is_visible' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $row) {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
            if (empty($row->slug) && ! empty($row->name)) {
                $row->slug = Str::slug($row->name).'-'.strtolower(Str::random(6));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function includedEvents(): HasMany
    {
        return $this->hasMany(BundleEvent::class)->orderBy('sort_order');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organisation_id', 'uuid');
    }

    public function remainingCapacity(): ?int
    {
        if ($this->total_capacity === null) {
            return null;
        }

        return max(0, $this->total_capacity - $this->sold_count);
    }
}
