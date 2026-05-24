<?php

declare(strict_types=1);

namespace App\Services\Distribution;

use App\Models\DistributionSale;
use App\Models\Distributor;
use App\Models\DistributorSpotAudit;
use App\Models\TicketCustodyLedgerEntry;
use Carbon\CarbonImmutable;

/**
 * Recomputes a distributor's composite trust score (0-100) from
 * recent behaviour. Higher score = more inventory we'll dispatch
 * per request, fewer spot audits, fewer escalations.
 *
 * Each component normalises to [0, 1] (1 = best). The composite is
 * a weighted sum per config('distribution.trust_score.weights') and
 * mapped to 0-100.
 */
class TrustScoreCalculator
{
    public function __construct(
        protected InventorySnapshotService $inventory,
    ) {}

    public function recompute(Distributor $distributor): int
    {
        $weights = (array) config('distribution.trust_score.weights', []);
        $window = CarbonImmutable::now()->subDays(30);

        $latencyScore = $this->dispatchReceiptLatencyScore($distributor, $window);
        $velocityScore = $this->salesVelocityScore($distributor, $window);
        $geoScore = $this->geoAnomalyScore($distributor, $window);
        $auditScore = $this->spotAuditPassRate($distributor, $window);
        $leakageScore = $this->leakageScore($distributor);

        $components = [
            'dispatch_receipt_latency' => $latencyScore,
            'sales_velocity_vs_peers' => $velocityScore,
            'geo_anomaly_rate' => $geoScore,
            'spot_audit_pass_rate' => $auditScore,
            'leakage_rate' => $leakageScore,
        ];

        $weighted = 0.0;
        $totalWeight = 0.0;
        foreach ($components as $key => $score) {
            $w = (float) ($weights[$key] ?? 0.0);
            $weighted += $score * $w;
            $totalWeight += $w;
        }

        $composite = $totalWeight > 0 ? $weighted / $totalWeight : 0.5;
        $score = (int) max(0, min(100, round($composite * 100)));

        $distributor->forceFill(['trust_score' => $score])->save();

        return $score;
    }

    protected function dispatchReceiptLatencyScore(Distributor $distributor, CarbonImmutable $since): float
    {
        $sla = (int) config('distribution.dispatch.transit_sla_hours', 72);
        $dispatches = $distributor->load('parent') // ensure relation hydrated
            ->id;

        // Average hours between issued_at and received_at, normalised:
        // 0h = 1.0, sla = 0.5, 2*sla = 0.0.
        $rows = \App\Models\TicketDispatch::query()
            ->where('to_distributor_id', $dispatches)
            ->where('status', \App\Models\TicketDispatch::STATUS_RECEIVED)
            ->where('issued_at', '>=', $since)
            ->whereNotNull('received_at')
            ->get(['issued_at', 'received_at']);

        if ($rows->isEmpty()) {
            return 0.5;
        }

        $avgHours = $rows->avg(fn ($r): float => $r->issued_at->diffInHours($r->received_at));

        return (float) max(0.0, min(1.0, 1.0 - ($avgHours / (2 * $sla))));
    }

    protected function salesVelocityScore(Distributor $distributor, CarbonImmutable $since): float
    {
        $count = DistributionSale::query()
            ->where('distributor_id', $distributor->id)
            ->where('sold_at', '>=', $since)
            ->count();

        // Sigmoid: 0 sales = 0.2, 100 = 0.5, 1000 = 0.9, asymptote 1.0.
        $x = $count + 1;
        return (float) (1 / (1 + exp(-(log10($x) - 1.5) * 2)));
    }

    protected function geoAnomalyScore(Distributor $distributor, CarbonImmutable $since): float
    {
        $sales = DistributionSale::query()
            ->where('distributor_id', $distributor->id)
            ->where('sold_at', '>=', $since)
            ->count();
        if ($sales === 0) {
            return 1.0;
        }

        $anomalies = DistributionSale::query()
            ->where('distributor_id', $distributor->id)
            ->where('sold_at', '>=', $since)
            ->where('outside_geofence', true)
            ->count();

        $rate = $anomalies / $sales;

        // 0% anomalies = 1.0; 10% = 0.5; 20%+ = 0.0.
        return (float) max(0.0, 1.0 - $rate * 5);
    }

    protected function spotAuditPassRate(Distributor $distributor, CarbonImmutable $since): float
    {
        $total = DistributorSpotAudit::query()
            ->where('distributor_id', $distributor->id)
            ->where('issued_at', '>=', $since)
            ->whereIn('status', [DistributorSpotAudit::STATUS_PASSED, DistributorSpotAudit::STATUS_FAILED, DistributorSpotAudit::STATUS_EXPIRED])
            ->count();

        if ($total === 0) {
            return 0.7; // neutral-positive for fresh distributors
        }

        $passed = DistributorSpotAudit::query()
            ->where('distributor_id', $distributor->id)
            ->where('issued_at', '>=', $since)
            ->where('status', DistributorSpotAudit::STATUS_PASSED)
            ->count();

        return (float) ($passed / $total);
    }

    protected function leakageScore(Distributor $distributor): float
    {
        $leakage = $this->inventory->leakageCount($distributor);
        $handled = TicketCustodyLedgerEntry::query()
            ->where('actor_type', TicketCustodyLedgerEntry::ACTOR_DISTRIBUTOR)
            ->where('actor_id', $distributor->id)
            ->where('event_type', TicketCustodyLedgerEntry::EVENT_RECEIVED)
            ->count();

        if ($handled === 0) {
            return 1.0;
        }

        $rate = $leakage / $handled;

        // 0% leakage = 1.0; 5% = 0.0 (very steep — leakage is the
        // single most damaging signal we have).
        return (float) max(0.0, 1.0 - $rate * 20);
    }
}
