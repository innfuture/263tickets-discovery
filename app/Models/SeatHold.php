<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-checkout-session lock on a specific seat. Unique on seat_id so
 * two carts can never claim the same seat — the constraint backs the
 * SeatedReservation guarantees.
 *
 * Lifecycle:
 *   - created on add-to-cart
 *   - extended on session activity (touch)
 *   - deleted on cart abandon / expire
 *   - converted on fulfilment: `order_item_id` set, the row remains
 *     so the seat → order_item lookup is one read
 */
class SeatHold extends Model
{
    use HasFactory;

    protected $fillable = [
        'seat_id', 'checkout_session_id', 'order_item_id', 'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class, 'checkout_session_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
