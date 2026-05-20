<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_id',
    'session_id',
    'ip_address',
    'user_agent',
    'country_code',
    'city',
    'region',
    'latitude',
    'longitude',
    'referrer',
    'utm_source',
    'utm_medium',
    'utm_campaign',
    'utm_term',
    'utm_content',
    'event_type',
    'event_target',
    'event_value',
    'event_metadata',
    'event_data',
])]
class EventPageView extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'event_data' => 'array',
            'event_metadata' => 'array',
            'event_value' => 'decimal:2',
            'latitude' => 'decimal:4',
            'longitude' => 'decimal:4',
            'created_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
