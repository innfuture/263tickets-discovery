<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ticket_category_id',
    'name',
    'discount_type',
    'value',
    'min_quantity',
    'max_uses',
    'uses_count',
    'starts_at',
    'expires_at',
    'is_active',
])]
class TicketDiscount extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'is_active' => 'boolean',
            'min_quantity' => 'integer',
            'max_uses' => 'integer',
            'uses_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TicketCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }
}
