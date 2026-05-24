<?php

declare(strict_types=1);

namespace App\Services\Scanning;

use App\Services\Scanning\Contracts\ScanHistory;
use App\Services\Scanning\Data\ScanContext;
use Carbon\CarbonInterface;

/**
 * Test double — set the canned values up-front and assert nothing
 * about the DB. Production code never binds this; tests use it via
 * direct constructor injection.
 */
class StubScanHistory implements ScanHistory
{
    public function __construct(
        public int $recentAdmittedCount = 0,
        public ?CarbonInterface $lastScanAt = null,
    ) {}

    public function recentAdmittedByDevice(ScanContext $context, int $seconds): int
    {
        return $this->recentAdmittedCount;
    }

    public function lastScanAtForTicket(ScanContext $context): ?CarbonInterface
    {
        return $this->lastScanAt;
    }
}
