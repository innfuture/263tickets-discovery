<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Scheduled door-staff window for an event. Cross-references with the
 * door-PIN auth so the scanner app can only sign in during the shift,
 * limiting blast radius if a PIN is exfiltrated.
 *
 * Status lifecycle:
 *   scheduled → active (when starts_at passed + staff signed in)
 *             → completed | no_show
 *             → cancelled
 */
class DoorStaffShift extends Model
{
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_NO_SHOW = 'no_show';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid', 'event_id', 'user_id', 'staff_name', 'door_label',
        'starts_at', 'ends_at', 'status',
        'expected_scan_count', 'actual_scan_count', 'notes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'expected_scan_count' => 'integer',
        'actual_scan_count' => 'integer',
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

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isWithinWindow(?\DateTimeInterface $at = null): bool
    {
        $at = $at ? \Carbon\CarbonImmutable::parse($at->format('c')) : \Carbon\CarbonImmutable::now();

        return $at->greaterThanOrEqualTo($this->starts_at)
            && $at->lessThanOrEqualTo($this->ends_at);
    }
}
