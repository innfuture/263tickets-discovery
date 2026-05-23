<?php

declare(strict_types=1);

namespace App\Services\Payments\Sandbox\Exceptions;

use App\Enums\SandboxState;
use App\Services\Payments\Exceptions\PaymentException;

/**
 * Raised when a caller asks for a state move the machine forbids
 * (capturing a voided auth, refunding a failed charge…). Carries
 * before/after for the audit log.
 */
class IllegalTransitionException extends PaymentException
{
    public function __construct(
        public readonly SandboxState $from,
        public readonly SandboxState $to,
        ?string $reason = null,
    ) {
        parent::__construct(
            sprintf('Illegal sandbox transition %s → %s%s',
                $from->value, $to->value, $reason ? " ({$reason})" : ''),
        );
    }
}
