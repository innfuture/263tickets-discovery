<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerMembershipPeriod extends Model
{
    use HasFactory;

    protected $table = 'membership_periods';

    protected $fillable = [
        'membership_id', 'order_id', 'starts_at', 'ends_at',
        'paid_cents', 'currency',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'paid_cents' => 'integer',
    ];

    public function membership(): BelongsTo
    {
        return $this->belongsTo(BuyerMembership::class, 'membership_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
