<?php

declare(strict_types=1);

namespace App\Services\Distribution\Exceptions;

use RuntimeException;

/**
 * Thrown when a ticket's requested next state is not reachable from
 * its current state per TicketLifecyclePolicy. The attempt is also
 * recorded in the ledger as a lifecycle_violation entry for audit.
 */
class IllegalCustodyTransition extends RuntimeException
{
    public function __construct(
        public readonly string $ticketUuid,
        public readonly string $fromState,
        public readonly string $toEvent,
    ) {
        parent::__construct(
            "Ticket {$ticketUuid} cannot transition from {$fromState} via {$toEvent}.",
        );
    }
}
