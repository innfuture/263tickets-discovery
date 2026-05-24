<?php

declare(strict_types=1);

namespace App\Services\Scanning\Rules;

use App\Services\Scanning\Contracts\FraudRule;
use App\Services\Scanning\Data\FraudVerdict;
use App\Services\Scanning\Data\ScanContext;

class VoidedTicketRule implements FraudRule
{
    public function id(): string
    {
        return 'voided_ticket';
    }

    public function evaluate(ScanContext $context): FraudVerdict
    {
        if ($context->ticket === null) {
            return FraudVerdict::allow($this->id());
        }

        if (! $context->ticket->is_voided) {
            return FraudVerdict::allow($this->id());
        }

        $reason = $context->ticket->void_reason
            ? ": {$context->ticket->void_reason}"
            : '.';

        return FraudVerdict::deny(
            'ticket_voided',
            "Ticket was voided{$reason}",
            severity: 7,
            rule: $this->id(),
        );
    }
}
