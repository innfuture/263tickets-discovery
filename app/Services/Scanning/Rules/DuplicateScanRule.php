<?php

declare(strict_types=1);

namespace App\Services\Scanning\Rules;

use App\Services\Scanning\Contracts\FraudRule;
use App\Services\Scanning\Contracts\ScanHistory;
use App\Services\Scanning\Data\FraudVerdict;
use App\Services\Scanning\Data\ScanContext;

/**
 * A ticket scanned again within the profile's `duplicate_window_seconds`
 * is almost always either a network retry or someone trying to slip a
 * second person in on the same QR. We WARN rather than DENY by default
 * — the gate operator decides — but a profile can tighten this by
 * supplying a longer window.
 *
 * Reads "last scanned at" via the ScanHistory contract so unit tests
 * can drive the timing scenarios without needing a real OfflineTicket.
 */
class DuplicateScanRule implements FraudRule
{
    public function __construct(protected ScanHistory $history) {}

    public function id(): string
    {
        return 'duplicate_scan';
    }

    public function evaluate(ScanContext $context): FraudVerdict
    {
        if ($context->ticket === null) {
            return FraudVerdict::allow($this->id());
        }

        $lastAt = $this->history->lastScanAtForTicket($context);
        if ($lastAt === null) {
            return FraudVerdict::allow($this->id());
        }

        $windowSeconds = (int) $context->profile->duplicate_window_seconds;
        if ($windowSeconds <= 0) {
            return FraudVerdict::allow($this->id());
        }

        // Always warn on a scanned-already ticket. Use the configured
        // window as the threshold for *severity* — recent re-scans are
        // far more suspicious than ones hours later. abs() because
        // Carbon's diffInSeconds is signed by which argument is later.
        $secondsSince = abs($context->serverAt->diffInSeconds($lastAt));
        $severity = $secondsSince <= $windowSeconds ? 4 : 2;

        return FraudVerdict::warn(
            'duplicate_scan',
            "Ticket already scanned {$lastAt->diffForHumans()}.",
            severity: $severity,
            rule: $this->id(),
        );
    }
}
