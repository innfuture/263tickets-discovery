<?php

declare(strict_types=1);

namespace App\Services\Distribution;

use App\Models\DistributionSale;
use App\Models\DistributorDevice;
use App\Services\Distribution\Exceptions\SaleException;
use Carbon\CarbonImmutable;

/**
 * Per-device sliding-window velocity guard. Trips on > N sales per
 * minute (configurable). The check is cheap (one indexed COUNT) and
 * runs before any DB writes so a misconfigured / compromised device
 * can't burn through inventory in a burst.
 */
class SalesVelocityGuard
{
    public function assertWithinLimits(DistributorDevice $device): void
    {
        $limit = (int) config('distribution.sales.max_sales_per_device_per_minute', 20);
        if ($limit <= 0) {
            return;
        }

        $cutoff = CarbonImmutable::now()->subMinute();
        $recent = DistributionSale::query()
            ->where('distributor_device_id', $device->id)
            ->where('sold_at', '>=', $cutoff)
            ->count();

        if ($recent >= $limit) {
            throw new SaleException(
                'velocity_exceeded',
                "Device {$device->uuid} exceeded sales velocity limit ({$recent}/{$limit} per minute).",
            );
        }
    }
}
