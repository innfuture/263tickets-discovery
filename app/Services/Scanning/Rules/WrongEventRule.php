<?php

declare(strict_types=1);

namespace App\Services\Scanning\Rules;

use App\Services\Scanning\Contracts\FraudRule;
use App\Services\Scanning\Data\FraudVerdict;
use App\Services\Scanning\Data\ScanContext;

/**
 * Denies a scan when the ticket's event is outside the scanner
 * profile's `allowed_event_ids`. Critical for multi-day festivals
 * where Friday's wristband shouldn't pass on Saturday.
 */
class WrongEventRule implements FraudRule
{
    public function id(): string
    {
        return 'wrong_event';
    }

    public function evaluate(ScanContext $context): FraudVerdict
    {
        if ($context->ticket === null || $context->event === null) {
            return FraudVerdict::allow($this->id());
        }

        if ($context->profile->canScanEvent((int) $context->event->id)) {
            return FraudVerdict::allow($this->id());
        }

        return FraudVerdict::deny(
            'wrong_event',
            'Ticket is for an event this scanner is not authorised for.',
            severity: 7,
            rule: $this->id(),
        );
    }
}
