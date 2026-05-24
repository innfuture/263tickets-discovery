<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StakeholderPayment extends Model
{
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid', 'event_stakeholder_engagement_id', 'description',
        'amount_cents', 'currency', 'due_at', 'status',
        'payment_transaction_id', 'paid_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
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

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(EventStakeholderEngagement::class, 'event_stakeholder_engagement_id');
    }
}
