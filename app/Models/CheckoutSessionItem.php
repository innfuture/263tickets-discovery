<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single (ticket-tier × quantity) line inside a checkout session.
 * One row per tier; "two GA tickets" is `quantity = 2`, not two rows.
 *
 * Holds against inventory while the parent session is open or paying.
 * The `TicketReservation` service is the only thing that should
 * create / mutate these rows.
 */
class CheckoutSessionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'checkout_session_id',
        'ticket_category_id',
        'quantity',
        'unit_price_cents',
        'currency',
        'line_total_cents',
        'price_locked_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_cents' => 'integer',
        'line_total_cents' => 'integer',
        'price_locked_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class, 'checkout_session_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }
}
