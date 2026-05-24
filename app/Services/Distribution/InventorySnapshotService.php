<?php

declare(strict_types=1);

namespace App\Services\Distribution;

use App\Models\DistributionSale;
use App\Models\Distributor;
use App\Models\DistributorInventorySnapshot;
use App\Models\TicketCustodyLedgerEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Materialises per-distributor / per-event inventory snapshots from
 * the custody ledger + sales table. Designed to be cheap enough to
 * run on every ledger insert (queued) but also exposed as a manual
 * rebuild for ops + audits.
 */
class InventorySnapshotService
{
    public function rebuildForDistributor(Distributor $distributor, ?int $eventId = null): DistributorInventorySnapshot
    {
        // Count ledger events involving this distributor, grouped by
        // event_type. Inner aggregation keeps query count at 1.
        $ledgerCounts = TicketCustodyLedgerEntry::query()
            ->selectRaw('event_type, COUNT(*) as c')
            ->where('actor_type', TicketCustodyLedgerEntry::ACTOR_DISTRIBUTOR)
            ->where('actor_id', $distributor->id)
            ->groupBy('event_type')
            ->pluck('c', 'event_type');

        $received = (int) ($ledgerCounts[TicketCustodyLedgerEntry::EVENT_RECEIVED] ?? 0);
        $transferredOut = (int) ($ledgerCounts[TicketCustodyLedgerEntry::EVENT_DISPATCHED] ?? 0);
        $voided = (int) ($ledgerCounts[TicketCustodyLedgerEntry::EVENT_VOIDED] ?? 0);
        $recovered = (int) ($ledgerCounts[TicketCustodyLedgerEntry::EVENT_RECOVERED] ?? 0);

        $salesQuery = DistributionSale::query()->where('distributor_id', $distributor->id);
        if ($eventId !== null) {
            $salesQuery->where('event_id', $eventId);
        }
        $sales = $salesQuery
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(amount_cents),0) as revenue, currency')
            ->groupBy('currency')
            ->first();

        $soldCount = (int) ($sales->c ?? 0);
        $revenueCents = (int) ($sales->revenue ?? 0);
        $currency = (string) ($sales->currency ?? $distributor->float_currency ?? 'USD');

        $onHand = max(0, $received - $transferredOut - $soldCount - $voided - $recovered);

        return DB::transaction(function () use ($distributor, $eventId, $received, $transferredOut, $soldCount, $voided, $recovered, $onHand, $revenueCents, $currency): DistributorInventorySnapshot {
            $snapshot = DistributorInventorySnapshot::query()->firstOrNew([
                'distributor_id' => $distributor->id,
                'event_id' => $eventId,
            ]);

            $snapshot->forceFill([
                'received_count' => $received,
                // We treat dispatched-by-this-distributor as transferred_out.
                'transferred_in_count' => 0,
                'transferred_out_count' => $transferredOut,
                'sold_count' => $soldCount,
                'voided_count' => $voided,
                'recovered_count' => $recovered,
                'on_hand_count' => $onHand,
                'gross_revenue_cents' => $revenueCents,
                'currency' => $currency,
                'computed_at' => CarbonImmutable::now(),
            ])->save();

            return $snapshot;
        });
    }

    /**
     * Leakage = tickets scanned at the gate with no recorded sale.
     * Returns the count for a distributor over the optional event.
     */
    public function leakageCount(Distributor $distributor, ?int $eventId = null): int
    {
        // Scanned UUIDs from the ledger ∩ tickets handled by this
        // distributor ∩ no DistributionSale row.
        $handled = TicketCustodyLedgerEntry::query()
            ->where('actor_type', TicketCustodyLedgerEntry::ACTOR_DISTRIBUTOR)
            ->where('actor_id', $distributor->id)
            ->whereIn('event_type', [
                TicketCustodyLedgerEntry::EVENT_RECEIVED,
            ])
            ->pluck('ticket_uuid');

        if ($handled->isEmpty()) {
            return 0;
        }

        $scanned = TicketCustodyLedgerEntry::query()
            ->where('event_type', TicketCustodyLedgerEntry::EVENT_SCANNED)
            ->whereIn('ticket_uuid', $handled)
            ->pluck('ticket_uuid');

        if ($scanned->isEmpty()) {
            return 0;
        }

        $sold = DistributionSale::query()
            ->where('distributor_id', $distributor->id)
            ->when($eventId !== null, fn ($q) => $q->where('event_id', $eventId))
            ->whereIn('ticket_uuid', $scanned)
            ->pluck('ticket_uuid');

        return $scanned->diff($sold)->count();
    }
}
