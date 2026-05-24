<?php

declare(strict_types=1);

namespace App\Services\Distribution;

use App\Models\TicketCustodyLedgerEntry as Ledger;

/**
 * Single source of truth for legal state transitions on a physical
 * ticket. Encoded as a `from_state => [allowed_next_events]` map so
 * the state machine is grep-able and reviewable in one place.
 *
 * The current state of a ticket is the event_type of its latest
 * ledger row (or `printed` implicitly for tickets that have not yet
 * had any movement recorded). State is computed on read, never stored
 * as a denormalised column — denormalisation is exactly where chain-
 * of-custody systems lose audit integrity.
 */
class TicketLifecyclePolicy
{
    /**
     * @var array<string, array<int, string>>
     */
    private const ALLOWED = [
        // Initial state — backoffice issues the dispatch.
        Ledger::EVENT_PRINTED => [
            Ledger::EVENT_DISPATCHED,
            Ledger::EVENT_VOIDED,
        ],
        Ledger::EVENT_DISPATCHED => [
            Ledger::EVENT_RECEIVED,
            // Recipient disputes the manifest — no state change at the
            // ticket level, but the dispatch row carries the dispute.
            // No ledger event for that; dispute lives on TicketDispatch.
        ],
        Ledger::EVENT_RECEIVED => [
            Ledger::EVENT_SOLD,
            Ledger::EVENT_TRANSFERRED,
            Ledger::EVENT_VOIDED,
            Ledger::EVENT_RECOVERED,
            Ledger::EVENT_SPOT_AUDIT_OK,
            Ledger::EVENT_SPOT_AUDIT_FAILED,
            Ledger::EVENT_GEO_ANOMALY,
        ],
        Ledger::EVENT_TRANSFERRED => [
            // Recipient receives — chain resumes.
            Ledger::EVENT_RECEIVED,
        ],
        Ledger::EVENT_SOLD => [
            Ledger::EVENT_ACTIVATED,
            // Refund flow can still void the sold ticket.
            Ledger::EVENT_VOIDED,
        ],
        Ledger::EVENT_ACTIVATED => [
            Ledger::EVENT_SCANNED,
            Ledger::EVENT_VOIDED,
        ],
        // Terminal states — no transitions out.
        Ledger::EVENT_SCANNED => [],
        Ledger::EVENT_VOIDED => [],
        Ledger::EVENT_RECOVERED => [],
    ];

    /**
     * Returns true if a ticket currently in `$fromState` may legally
     * transition via `$event`. Audit-only events (spot audits, geo
     * anomalies) are allowed from any non-terminal state.
     */
    public function canTransition(string $fromState, string $event): bool
    {
        if ($this->isAuditOnly($event) && ! $this->isTerminal($fromState)) {
            return true;
        }

        $allowed = self::ALLOWED[$fromState] ?? [];

        return in_array($event, $allowed, true);
    }

    public function isTerminal(string $state): bool
    {
        return ($this->allowedNext($state)) === [];
    }

    /**
     * @return array<int, string>
     */
    public function allowedNext(string $state): array
    {
        return self::ALLOWED[$state] ?? [];
    }

    public function isAuditOnly(string $event): bool
    {
        return in_array($event, [
            Ledger::EVENT_SPOT_AUDIT_OK,
            Ledger::EVENT_SPOT_AUDIT_FAILED,
            Ledger::EVENT_GEO_ANOMALY,
            Ledger::EVENT_LIFECYCLE_VIOLATION,
        ], true);
    }
}
