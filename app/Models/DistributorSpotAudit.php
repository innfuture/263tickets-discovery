<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Periodic challenge: "Within 24h, photograph these specific tickets
 * with today's date-code overlay." Pass/fail is recorded in the
 * custody ledger; the audit row carries the operational metadata
 * (challenge tickets, response payload, due date).
 */
class DistributorSpotAudit extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PASSED = 'passed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'uuid', 'distributor_id',
        'challenge_ticket_uuids',
        'issued_at', 'due_at', 'responded_at',
        'status', 'response_payload', 'notes',
    ];

    protected $casts = [
        'challenge_ticket_uuids' => 'array',
        'response_payload' => 'array',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $row): void {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Distributor, $this> */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }
}
