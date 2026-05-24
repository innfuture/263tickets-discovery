<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Recipe for generating recurring Event instances (weekly comedy
 * night, monthly meetup, daily museum admission slots). A scheduled
 * job (`GenerateRecurringEventInstancesJob`) walks `is_active`
 * templates and clones `source_event_id` per the cadence.
 */
class EventTemplate extends Model
{
    use HasFactory;

    public const CADENCE_DAILY = 'daily';

    public const CADENCE_WEEKLY = 'weekly';

    public const CADENCE_MONTHLY = 'monthly';

    public const CADENCE_NTH_WEEKDAY = 'nth_weekday_of_month';

    protected $fillable = [
        'uuid', 'organisation_id', 'name', 'source_event_id',
        'cadence', 'cadence_meta', 'repeat_until', 'max_instances',
        'instances_created', 'last_generated_at', 'next_run_at',
        'is_active',
    ];

    protected $casts = [
        'cadence_meta' => 'array',
        'repeat_until' => 'datetime',
        'max_instances' => 'integer',
        'instances_created' => 'integer',
        'last_generated_at' => 'datetime',
        'next_run_at' => 'datetime',
        'is_active' => 'boolean',
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

    public function sourceEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'source_event_id');
    }
}
