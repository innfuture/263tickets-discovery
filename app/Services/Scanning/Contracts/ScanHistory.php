<?php

declare(strict_types=1);

namespace App\Services\Scanning\Contracts;

use App\Services\Scanning\Data\ScanContext;
use Carbon\CarbonInterface;

/**
 * Read-only slice of scan history that the DB-touching FraudRules
 * (Velocity, DuplicateScan) need.
 *
 * Pulled behind an interface so the rules can be unit-tested with a
 * canned stub instead of standing up a full Eloquent fixture. The
 * default DB-backed implementation lives in `EloquentScanHistory`;
 * tests bind `StubScanHistory` and drive returns directly.
 *
 * Methods all take ScanContext so a future, more sophisticated impl
 * can scope by event / venue / hour without changing call sites.
 */
interface ScanHistory
{
    /**
     * Count of admitted (non-deny) scans by this device in the last
     * `$seconds` seconds.
     */
    public function recentAdmittedByDevice(ScanContext $context, int $seconds): int;

    /**
     * Last server timestamp this exact ticket was scanned, or null if
     * never scanned. Used by DuplicateScanRule to compute the gap.
     */
    public function lastScanAtForTicket(ScanContext $context): ?CarbonInterface;
}
