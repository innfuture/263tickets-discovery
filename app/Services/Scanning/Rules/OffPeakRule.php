<?php

declare(strict_types=1);

namespace App\Services\Scanning\Rules;

use App\Services\Scanning\Contracts\FraudRule;
use App\Services\Scanning\Data\FraudVerdict;
use App\Services\Scanning\Data\ScanContext;

/**
 * Warns when a scan happens outside the event's scheduled window
 * — earlier than `doors_open_at` (or `starts_at` if doors are unset),
 * or later than `ends_at` plus a grace.
 *
 * Most off-peak scans are real (early staff, late refunds); we WARN
 * not DENY so operators can decide.
 */
class OffPeakRule implements FraudRule
{
    public function id(): string
    {
        return 'off_peak';
    }

    public function evaluate(ScanContext $context): FraudVerdict
    {
        $event = $context->event;
        if ($event === null || $event->starts_at === null) {
            return FraudVerdict::allow($this->id());
        }

        $now = $context->serverAt;
        $openAt = $event->doors_open_at ?? $event->starts_at;
        $endAt = $event->ends_at ?? $event->starts_at->copy()->addHours(8);

        $graceMinutes = (int) config('scanning.off_peak_grace_minutes', 60);
        $earlyCutoff = $openAt->copy()->subMinutes($graceMinutes);
        $lateCutoff = $endAt->copy()->addMinutes($graceMinutes);

        if ($now->between($earlyCutoff, $lateCutoff)) {
            return FraudVerdict::allow($this->id());
        }

        $when = $now < $earlyCutoff
            ? "before doors open ({$openAt->diffForHumans($now)})"
            : "after the event ended ({$endAt->diffForHumans($now)})";

        return FraudVerdict::warn(
            'off_peak',
            "Scan {$when}.",
            severity: 1,
            rule: $this->id(),
        );
    }
}
