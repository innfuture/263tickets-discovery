<?php

declare(strict_types=1);

namespace App\Services\Scanning\Rules;

use App\Services\Scanning\Contracts\FraudRule;
use App\Services\Scanning\Contracts\ScanHistory;
use App\Services\Scanning\Data\FraudVerdict;
use App\Services\Scanning\Data\ScanContext;

/**
 * Bot-style scans look like: one device firing N tickets per minute
 * with no human cadence between them. Counts the device's allowed
 * verdicts in the last 60s and compares to the profile's
 * `max_scans_per_minute`. Above the threshold → WARN; 2× threshold
 * → DENY.
 *
 * Reads scan history through the ScanHistory contract so tests can
 * inject a stub instead of populating a scan_events fixture.
 */
class VelocityRule implements FraudRule
{
    public function __construct(protected ScanHistory $history) {}

    public function id(): string
    {
        return 'velocity';
    }

    public function evaluate(ScanContext $context): FraudVerdict
    {
        $limit = (int) $context->profile->max_scans_per_minute;
        if ($limit <= 0) {
            return FraudVerdict::allow($this->id());
        }

        $recent = $this->history->recentAdmittedByDevice($context, 60);

        if ($recent >= $limit * 2) {
            return FraudVerdict::deny(
                'rate_exceeded',
                "Device has scanned {$recent} tickets in the last minute (limit {$limit}). Cooling down.",
                severity: 6,
                rule: $this->id(),
            );
        }

        if ($recent >= $limit) {
            return FraudVerdict::warn(
                'rate_high',
                "Device pace is above the configured {$limit}/min threshold ({$recent} in last minute).",
                severity: 3,
                rule: $this->id(),
            );
        }

        return FraudVerdict::allow($this->id());
    }
}
