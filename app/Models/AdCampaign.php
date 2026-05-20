<?php

namespace App\Models;

use App\Enums\AdCampaignStatus;
use App\Enums\AdPlatform;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'event_id',
    'owner_type',
    'owner_id',
    'organisation_id',
    'created_by_user_id',
    'name',
    'platform',
    'campaign_status',
    'budget_daily',
    'budget_total',
    'budget_currency',
    'runs_from',
    'runs_until',
    'targeting',
    'creative_assets',
    'platform_config',
    'external_campaign_id',
    'external_ad_account_id',
    'external_ad_set_id',
    'metrics',
    'metrics_synced_at',
])]
class AdCampaign extends Model
{
    use HasFactory, SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (AdCampaign $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }

            // Keep event_id and owner columns in sync when one is set.
            if ($model->event_id && empty($model->owner_type)) {
                $model->owner_type = Event::class;
                $model->owner_id = $model->event_id;
            }

            if (
                $model->owner_type === Event::class
                && $model->owner_id
                && empty($model->event_id)
            ) {
                $model->event_id = $model->owner_id;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'platform' => AdPlatform::class,
            'campaign_status' => AdCampaignStatus::class,
            'budget_daily' => 'decimal:2',
            'budget_total' => 'decimal:2',
            'runs_from' => 'datetime',
            'runs_until' => 'datetime',
            'targeting' => 'array',
            'creative_assets' => 'array',
            'platform_config' => 'array',
            'metrics' => 'array',
            'metrics_synced_at' => 'datetime',
        ];
    }

    /**
     * Polymorphic owner — any model that has campaigns (events, venues,
     * organisations, etc.). Use `$model->morphMany(AdCampaign::class, 'owner')`
     * on the parent.
     *
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Convenience accessor for event-owned campaigns. Returns null when the
     * owner is something other than an Event.
     *
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
