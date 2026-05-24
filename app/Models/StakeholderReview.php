<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StakeholderReview extends Model
{
    use HasFactory;

    public const DIRECTION_ORG_TO_STAKEHOLDER = 'organizer_to_stakeholder';

    public const DIRECTION_STAKEHOLDER_TO_ORG = 'stakeholder_to_organizer';

    protected $fillable = [
        'event_stakeholder_engagement_id', 'direction',
        'rating', 'comment', 'criteria_ratings', 'is_public',
        'author_user_id',
    ];

    protected $casts = [
        'rating' => 'integer',
        'criteria_ratings' => 'array',
        'is_public' => 'boolean',
    ];

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(EventStakeholderEngagement::class, 'event_stakeholder_engagement_id');
    }
}
