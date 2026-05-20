<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ticket_category_id',
    'code',
    'discount_type',
    'value',
    'max_uses',
    'uses_count',
    'expires_at',
    'auto_stop_criteria',
    'is_active',
])]
class TicketPromoCode extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'is_active' => 'boolean',
            'max_uses' => 'integer',
            'uses_count' => 'integer',
            'expires_at' => 'datetime',
            'auto_stop_criteria' => 'array',
        ];
    }

    /** @return BelongsTo<TicketCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }
}
