<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Append-only row in the per-ticket chain-of-custody ledger. Updates
 * and deletes are blocked at the model boundary; even with direct DB
 * access, a SQL injection in the main app can't rewrite history
 * because integrity is verified by walking the hash chain.
 *
 * Reads go via TicketCustodyLedger::history($ticketUuid); writes only
 * via TicketCustodyLedger::append(...) which computes prev_hash /
 * this_hash and asserts the chain remains contiguous.
 */
class TicketCustodyLedgerEntry extends Model
{
    use HasFactory;

    protected $table = 'ticket_custody_ledger';

    // Lifecycle events.
    public const EVENT_PRINTED = 'printed';
    public const EVENT_DISPATCHED = 'dispatched';
    public const EVENT_RECEIVED = 'received';
    public const EVENT_TRANSFERRED = 'transferred';
    public const EVENT_SOLD = 'sold';
    public const EVENT_ACTIVATED = 'activated';
    public const EVENT_SCANNED = 'scanned';
    public const EVENT_VOIDED = 'voided';
    public const EVENT_RECOVERED = 'recovered';
    // Audit + anomaly events (do not change state, but contribute to
    // trust score and surface in dashboards).
    public const EVENT_SPOT_AUDIT_OK = 'spot_audit_ok';
    public const EVENT_SPOT_AUDIT_FAILED = 'spot_audit_failed';
    public const EVENT_GEO_ANOMALY = 'geo_anomaly';
    public const EVENT_LIFECYCLE_VIOLATION = 'lifecycle_violation';

    public const ACTOR_ORGANIZATION = 'organization';
    public const ACTOR_DISTRIBUTOR = 'distributor';
    public const ACTOR_DEVICE = 'distributor_device';
    public const ACTOR_SYSTEM = 'system';
    public const ACTOR_BACKOFFICE_USER = 'backoffice_user';

    protected $fillable = [
        'ticket_uuid', 'sequence', 'event_type',
        'actor_type', 'actor_id', 'payload',
        'prev_hash', 'this_hash',
        'signature', 'signing_key_id',
        'occurred_at', 'recorded_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'recorded_at' => 'datetime',
        'sequence' => 'integer',
    ];

    public $timestamps = true;

    protected static function boot(): void
    {
        parent::boot();

        // Belt + braces — the service layer is the proper enforcement
        // point, but a stray ->update() / ->delete() elsewhere in the
        // codebase would silently break ledger integrity. Trip loudly.
        static::updating(function (): bool {
            throw new RuntimeException('TicketCustodyLedgerEntry is append-only; updates are forbidden.');
        });
        static::deleting(function (): bool {
            throw new RuntimeException('TicketCustodyLedgerEntry is append-only; deletes are forbidden.');
        });
    }
}
