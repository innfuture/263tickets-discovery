<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventEmbedding extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'model', 'embedding', 'content_hash', 'generated_at',
    ];

    protected $casts = [
        'embedding' => 'array',
        'generated_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
