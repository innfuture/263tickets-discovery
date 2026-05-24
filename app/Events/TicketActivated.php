<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\OfflineTicket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a physical ticket transitions to the `activated` state
 * via a sale. The scanner-edge integration observes this and pushes
 * an entry into the ACTIVATED_TICKETS KV namespace so the edge can
 * answer scans positively without an origin round-trip.
 *
 * Activation-on-sale is the keystone anti-counterfeit measure: a
 * photocopy of an unsold ticket fails at the gate because the
 * activation is a server-side fact, not a property of the printed
 * artifact.
 */
class TicketActivated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly OfflineTicket $ticket,
        public readonly ?string $saleUuid = null,
    ) {}
}
