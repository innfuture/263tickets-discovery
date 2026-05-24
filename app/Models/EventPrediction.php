<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPrediction extends Model
{
    use HasFactory;

    public const TYPE_SELLOUT_ETA = 'sellout_eta';

    public const TYPE_TICKETS_AT_DOOR = 'tickets_at_door';

    public const TYPE_OPTIMAL_PRICE = 'optimal_price';

    protected $fillable = [
        'event_id', 'prediction_type', 'value', 'confidence',
        'model_version', 'computed_at',
    ];

    protected $casts = [
        'value' => 'array',
        'confidence' => 'decimal:3',
        'computed_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
