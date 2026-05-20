<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ticket_category_id', 'currency_code', 'price'])]
class TicketCurrencyPrice extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<TicketCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }
}
