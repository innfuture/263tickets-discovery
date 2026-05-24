<?php

declare(strict_types=1);

namespace App\Services\Scanning\Rules;

use App\Services\Scanning\Contracts\FraudRule;
use App\Services\Scanning\Data\FraudVerdict;
use App\Services\Scanning\Data\ScanContext;

class TicketNotFoundRule implements FraudRule
{
    public function id(): string
    {
        return 'ticket_not_found';
    }

    public function evaluate(ScanContext $context): FraudVerdict
    {
        if ($context->ticket !== null) {
            return FraudVerdict::allow($this->id());
        }

        return FraudVerdict::deny(
            'ticket_not_found',
            'No ticket matches this payload — possible forgery or wrong event.',
            severity: 8,
            rule: $this->id(),
        );
    }
}
