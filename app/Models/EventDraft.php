<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EventDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid', 'event_id', 'ydoc_base64', 'version',
        'last_editor_user_id',
    ];

    protected $casts = [
        'version' => 'integer',
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

    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_editor_user_id');
    }
}
