<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 24h-TTL response cache (§15 #8). A retried call carrying the same
 * `Idempotency-Key` returns the original response byte-for-byte.
 */
class SandboxIdempotencyKey extends Model
{
    use HasFactory;

    protected $table = 'sandbox_idempotency_keys';

    protected $fillable = [
        'sandbox_merchant_id',
        'key',
        'request_hash',
        'response_status',
        'response_body',
        'expires_at',
    ];

    protected $casts = [
        'response_body' => 'array',
        'expires_at' => 'datetime',
        'response_status' => 'integer',
    ];
}
