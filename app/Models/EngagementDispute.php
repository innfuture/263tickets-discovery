<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EngagementDispute extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const RAISED_BY_ORGANIZER = 'organizer';

    public const RAISED_BY_STAKEHOLDER = 'stakeholder';

    public const RESOLUTION_UPHELD = 'upheld';

    public const RESOLUTION_PARTIAL = 'partial';

    public const RESOLUTION_REJECTED = 'rejected';

    protected $fillable = [
        'uuid', 'event_stakeholder_engagement_id',
        'raised_by_type', 'raised_by_id', 'reason_code',
        'initial_statement', 'evidence', 'status',
        'resolution', 'resolution_notes',
        'resolved_by_user_id', 'resolved_at',
    ];

    protected $casts = [
        'evidence' => 'array',
        'resolved_at' => 'datetime',
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
