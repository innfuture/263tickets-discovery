<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Optional purchasable extras on an event — parking, t-shirts, drink
 * vouchers, donations. Separate from TicketCategory because they
 * don't produce a scannable ticket and aren't gated by capacity in
 * the same way.
 *
 * `requires_ticket=true` means the addon can only be added to a cart
 * that already contains at least one ticket — used for parking-style
 * extras. Donations / merch leave it false.
 */
class EventAddon extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'event_id', 'organisation_id', 'name', 'description',
        'price_cents', 'currency', 'stock', 'sold_count', 'min_per_order',
        'max_per_order', 'requires_ticket', 'is_visible', 'sort_order',
        'image_path',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'stock' => 'integer',
        'sold_count' => 'integer',
        'min_per_order' => 'integer',
        'max_per_order' => 'integer',
        'requires_ticket' => 'boolean',
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
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

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function remainingStock(): ?int
    {
        if ($this->stock === null) {
            return null;
        }

        return max(0, $this->stock - $this->sold_count);
    }
}
