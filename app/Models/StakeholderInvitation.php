<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StakeholderInvitation extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'uuid', 'event_id', 'stakeholder_id', 'invited_by_user_id',
        'engagement_type', 'message', 'proposed_terms', 'status',
        'expires_at', 'responded_at',
    ];

    protected $casts = [
        'proposed_terms' => 'array',
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
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

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function stakeholder(): BelongsTo
    {
        return $this->belongsTo(Stakeholder::class);
    }
}
