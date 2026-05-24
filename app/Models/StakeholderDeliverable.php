<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StakeholderDeliverable extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'uuid', 'event_stakeholder_engagement_id', 'title', 'description',
        'due_at', 'status', 'submission_notes', 'submission_attachments',
        'submitted_at', 'approved_by_user_id', 'approved_at', 'review_notes',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'submission_attachments' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
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
