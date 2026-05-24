<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Event;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Cross-organisation aggregate analytics with Laplace-noise
 * differential privacy. Lets the platform expose "average organizer
 * in your category sold X tickets last month" without leaking any
 * one organizer's actual numbers.
 *
 * Privacy budget (epsilon) controls the trade-off:
 *   - low epsilon (e.g. 0.5)   strong privacy, noisier numbers
 *   - high epsilon (e.g. 5.0)  weaker privacy, more accurate
 *
 * Defaults are tuned for "useful but not identifying" — epsilon=1.0
 * + minimum group size of 10 organizations. Queries with smaller
 * groups return `null` so a single-org cohort can never leak.
 */
class DifferentialPrivacyAnalytics
{
    public function __construct(
        protected float $epsilon = 1.0,
        protected int $minGroupSize = 10,
    ) {}

    /**
     * @return array{
     *   group_size: int,
     *   avg_tickets_sold: float|null,
     *   avg_revenue_cents: float|null,
     *   epsilon: float,
     *   noisy: bool,
     * }
     */
    public function categoryAverages(?int $categoryId, ?string $countryCode = null, ?int $monthsBack = 1): array
    {
        $cutoff = now()->subMonthsNoOverflow(max(1, (int) $monthsBack));

        $eventIds = Event::query()
            ->when($categoryId, fn ($q, $c) => $q->where('category_id', $c))
            ->when($countryCode, fn ($q, $cc) => $q->where('country_code', strtoupper($cc)))
            ->where('starts_at', '>=', $cutoff)
            ->pluck('id', 'organisation_id');

        $groupSize = $eventIds->keys()->unique()->count();

        if ($groupSize < $this->minGroupSize) {
            return [
                'group_size' => $groupSize,
                'avg_tickets_sold' => null,
                'avg_revenue_cents' => null,
                'epsilon' => $this->epsilon,
                'noisy' => false,
                'reason' => 'group_too_small',
            ];
        }

        $idList = $eventIds->values()->all();

        $rawTickets = (int) Event::query()->whereIn('id', $idList)->sum('tickets_sold_count');
        $rawRevenue = (int) Order::query()
            ->whereIn('event_id', $idList)
            ->where('status', 'paid')
            ->sum('total_cents');

        $avgTickets = $rawTickets / $groupSize;
        $avgRevenue = $rawRevenue / $groupSize;

        // Laplace noise — sensitivity is one organisation's contribution
        // / group_size. We use a conservative sensitivity ceiling.
        $ticketSensitivity = 10_000 / $groupSize;
        $revenueSensitivity = 1_000_000 / $groupSize; // cents

        return [
            'group_size' => $groupSize,
            'avg_tickets_sold' => round($avgTickets + $this->laplaceNoise($ticketSensitivity), 1),
            'avg_revenue_cents' => round($avgRevenue + $this->laplaceNoise($revenueSensitivity), 0),
            'epsilon' => $this->epsilon,
            'noisy' => true,
        ];
    }

    /**
     * Standard Laplace noise sampler. Scale = sensitivity / epsilon.
     */
    protected function laplaceNoise(float $sensitivity): float
    {
        $scale = $sensitivity / max($this->epsilon, 0.001);
        $u = (random_int(0, PHP_INT_MAX) / PHP_INT_MAX) - 0.5;

        return -1 * $scale * ($u < 0 ? -1 : 1) * log(1 - 2 * abs($u));
    }
}
