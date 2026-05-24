<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Caches the response of an Idempotency-Key'd POST so retries return
 * the same body without re-running the action. Required for n8n's
 * burst-retry pattern.
 */
class AutomationIdempotencyKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'automation_token_id', 'key', 'request_hash',
        'response_status', 'response_body',
    ];

    protected $casts = [
        'response_body' => 'array',
        'response_status' => 'integer',
    ];
}
