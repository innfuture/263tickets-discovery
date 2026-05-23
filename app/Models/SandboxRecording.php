<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Captured production responses replayed by the sandbox for the
 * Diff-vs-prod tab (§4.3) and for drift detection. Uniqueness on
 * `(emulate, operation, scenario)` so a single fixture wins per slot.
 */
class SandboxRecording extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'sandbox_merchant_id',
        'slug',
        'emulate',
        'operation',
        'scenario',
        'request_snapshot',
        'response_status',
        'response_headers',
        'response_body',
        'source',
        'notes',
    ];

    protected $casts = [
        'request_snapshot' => 'array',
        'response_headers' => 'array',
        'response_body' => 'array',
        'response_status' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $row) {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::ulid();
            }
            if (empty($row->slug)) {
                $row->slug = sprintf('%s/%s/%s',
                    $row->emulate ?? 'unknown',
                    $row->operation ?? 'unknown',
                    $row->scenario ?? 'default',
                );
            }
        });
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(SandboxMerchant::class, 'sandbox_merchant_id');
    }
}
