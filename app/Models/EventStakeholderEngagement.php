<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * The contracted relationship between an Event and a Stakeholder.
 * Lives independently of the invitation/application that produced it —
 * each engagement has its own deliverables, payments, documents.
 */
class EventStakeholderEngagement extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_DISPUTED = 'disputed';

    protected $fillable = [
        'uuid', 'event_id', 'stakeholder_id',
        'stakeholder_invitation_id', 'stakeholder_application_id',
        'engagement_type', 'tier', 'terms', 'agreed_amount_cents',
        'currency', 'status', 'starts_at', 'ends_at',
        'completed_at', 'cancelled_at',
    ];

    protected $casts = [
        'terms' => 'array',
        'agreed_amount_cents' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
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

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function stakeholder(): BelongsTo
    {
        return $this->belongsTo(Stakeholder::class);
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(StakeholderDeliverable::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(StakeholderPayment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(StakeholderReview::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StakeholderDocument::class);
    }
}
