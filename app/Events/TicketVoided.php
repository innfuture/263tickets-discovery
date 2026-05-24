<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\OfflineTicket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever a ticket transitions to voided state. The
 * PushVoidedTicketToEdgeListener observes this and propagates the
 * void to the scanner-edge KV namespace so subsequent scans are
 * denied at the edge without an origin round-trip.
 *
 * `reason` is a short code (buyer_self_void, organizer_void,
 * fraud_rule, refund_processed) so the scanner-edge can surface
 * a meaningful message.
 */
class TicketVoided
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly OfflineTicket $ticket,
        public readonly string $reason = 'voided',
    ) {}
}
