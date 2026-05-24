<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\CheckoutSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever inventory frees up — abandoned cart, manual cancel,
 * refund. NotifyWaitlistOnCapacityReleasedJob observes this and
 * notifies as many pending waitlist entries as the freed capacity
 * allows.
 */
class CapacityReleased
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly CheckoutSession $session,
    ) {}
}
