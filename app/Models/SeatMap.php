<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A reusable seating chart for one or more events. The `layout` JSON
 * is whatever the renderer expects (SVG view-box + named zones).
 *
 * One SeatMap → many Seats. An event can have its own map or share
 * one with sibling events (recurring shows in the same venue).
 */
class SeatMap extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'organisation_id', 'event_id', 'name', 'layout', 'zones',
    ];

    protected $casts = [
        'layout' => 'array',
        'zones' => 'array',
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

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }
}
