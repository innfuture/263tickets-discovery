<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One queued domain-event delivery, written inside the same DB
 * transaction as the originating mutation. A separate worker drains
 * the table — guarantees at-least-once delivery even when the queue
 * is down between commit and dispatch.
 *
 * Table name is `webhook_outbox` (the conventional "outbox pattern"
 * name); the model carries the longer `WebhookOutboxEntry` so it
 * doesn't read as plural.
 */
class WebhookOutboxEntry extends Model
{
    use HasFactory;

    protected $table = 'webhook_outbox';

    public const STATUS_PENDING = 'pending';

    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'uuid', 'event_type', 'organization_id', 'payload',
        'status', 'attempts', 'last_error', 'available_at',
        'dispatched_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'available_at' => 'datetime',
        'dispatched_at' => 'datetime',
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
}
