<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SandboxWebhookOutbox extends Model
{
    use HasFactory;

    protected $table = 'sandbox_webhook_outbox';

    protected $fillable = [
        'uuid',
        'event_id',
        'sandbox_merchant_id',
        'sandbox_transaction_id',
        'type',
        'emulate',
        'payload',
        'headers',
        'signature',
        'scheduled_for',
        'last_attempt_at',
        'attempts',
        'status',
        'response_status',
        'response_body',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'scheduled_for' => 'datetime',
        'last_attempt_at' => 'datetime',
        'attempts' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $row) {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::ulid();
            }
            if (empty($row->event_id)) {
                $row->event_id = 'evt_sbx_'.bin2hex(random_bytes(12));
            }
        });
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(SandboxMerchant::class, 'sandbox_merchant_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(SandboxTransaction::class, 'sandbox_transaction_id');
    }
}
