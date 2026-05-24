<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Buyer-initiated refund request. The actual financial side is the
 * existing PaymentRefund — this row is the workflow ticket the
 * organizer reviews + approves/rejects.
 */
class RefundRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'uuid', 'order_id', 'organisation_id', 'reason_code', 'notes',
        'contact_email', 'status', 'payment_refund_id', 'reviewed_by_user_id',
        'review_notes', 'reviewed_at', 'ip_address',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentRefund(): BelongsTo
    {
        return $this->belongsTo(PaymentRefund::class, 'payment_refund_id');
    }
}
