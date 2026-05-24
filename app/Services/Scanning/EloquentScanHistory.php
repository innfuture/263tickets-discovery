<?php

declare(strict_types=1);

namespace App\Services\Scanning;

use App\Models\ScanEvent;
use App\Services\Scanning\Contracts\ScanHistory;
use App\Services\Scanning\Data\ScanContext;
use Carbon\CarbonInterface;

/**
 * Production ScanHistory — reads from scan_events + offline_tickets.
 * Bound as the default ScanHistory in the AppServiceProvider.
 */
class EloquentScanHistory implements ScanHistory
{
    public function recentAdmittedByDevice(ScanContext $context, int $seconds): int
    {
        return (int) ScanEvent::query()
            ->where('scanner_device_id', $context->device->id)
            ->where('verdict', '!=', 'deny')
            ->where('created_at', '>=', $context->serverAt->subSeconds($seconds))
            ->count();
    }

    public function lastScanAtForTicket(ScanContext $context): ?CarbonInterface
    {
        $ticket = $context->ticket;

        // `offline_tickets.scanned_at` is the cheap "first scan"
        // sentinel. For the duplicate rule we want it as-is — a
        // null means the ticket has never been scanned, anything
        // else is the earliest admission.
        return $ticket?->scanned_at;
    }
}
