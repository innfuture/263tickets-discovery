<?php

declare(strict_types=1);

namespace App\Services\Payments\Contracts;

use App\Services\Payments\Data\StatusResult;

interface PollsTransactionStatus
{
    /**
     * Look up the current state of an in-flight transaction. Used by
     * the reconciliation job for anything still PENDING past the stale
     * threshold, and as a fallback when webhooks are missed.
     */
    public function status(string $reference, ?string $gatewayReference = null): StatusResult;
}
