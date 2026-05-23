<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentRefund extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'payment_transaction_id',
        'reference',
        'gateway_reference',
        'status',
        'amount_minor',
        'currency',
        'reason',
        'initiated_by',
        'metadata',
        'last_response',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'metadata' => 'array',
        'last_response' => 'array',
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

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
