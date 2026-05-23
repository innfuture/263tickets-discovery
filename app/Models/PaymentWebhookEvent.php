<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Persisted record of every inbound webhook. Two purposes:
 *
 *   1. Idempotency — `signature_hash` (sha256 of raw body) is uniqued
 *      so retries from the gateway do not re-apply effects.
 *   2. Audit — every payload, header, and processing outcome is
 *      replay-able from this table without touching the gateway.
 *
 * The asynchronous side effects (notifying the order, sending receipts)
 * happen in ProcessWebhookEventJob, dispatched after this row is saved.
 */
class PaymentWebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'gateway',
        'event_type',
        'reference',
        'gateway_reference',
        'status',
        'signature_hash',
        'payload',
        'headers',
        'processing_status',
        'processing_error',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'processed_at' => 'datetime',
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
