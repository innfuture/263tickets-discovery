<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Distribution;

use App\Http\Controllers\Controller;
use App\Models\Distributor;
use App\Models\DistributorInventorySnapshot;
use App\Services\Distribution\InventorySnapshotService;
use App\Services\Distribution\TicketCustodyLedger;
use App\Services\Distribution\TrustScoreCalculator;
use Illuminate\Http\JsonResponse;

/**
 * Backoffice read-only forensics surfaces:
 *
 *   GET /backoffice/distribution/tickets/{uuid}/lifecycle
 *       Full per-ticket chain — every state transition, signed by
 *       which actor, with timestamps. The page an investigator opens
 *       when a dispute lands.
 *
 *   GET /backoffice/distribution/distributors/{uuid}/scorecard
 *       Composite trust score + the underlying components + recent
 *       inventory + leakage + anomaly counts.
 *
 *   GET /backoffice/distribution/tickets/{uuid}/verify
 *       Re-walks the ticket's chain and confirms hash links + payload
 *       hashes are intact. Returns null on success or the first
 *       violation description.
 *
 * Auth elsewhere (Spatie permission middleware on the route).
 */
class BackofficeForensicsController extends Controller
{
    public function __construct(
        protected TicketCustodyLedger $ledger,
        protected InventorySnapshotService $inventory,
        protected TrustScoreCalculator $trust,
    ) {}

    public function ticketLifecycle(string $uuid): JsonResponse
    {
        $entries = $this->ledger->history($uuid);
        if ($entries->isEmpty()) {
            return response()->json(['error' => 'no_history'], 404);
        }

        return response()->json([
            'data' => [
                'ticket_uuid' => $uuid,
                'current_state' => $this->ledger->currentState($uuid),
                'entries' => $entries->map(fn ($entry): array => [
                    'sequence' => $entry->sequence,
                    'event_type' => $entry->event_type,
                    'actor_type' => $entry->actor_type,
                    'actor_id' => $entry->actor_id,
                    'payload' => $entry->payload,
                    'prev_hash' => $entry->prev_hash,
                    'this_hash' => $entry->this_hash,
                    'signing_key_id' => $entry->signing_key_id,
                    'occurred_at' => $entry->occurred_at?->toIso8601String(),
                    'recorded_at' => $entry->recorded_at?->toIso8601String(),
                ])->all(),
            ],
        ]);
    }

    public function verifyChain(string $uuid): JsonResponse
    {
        $violation = $this->ledger->verifyChain($uuid);

        return response()->json([
            'data' => [
                'ticket_uuid' => $uuid,
                'intact' => $violation === null,
                'violation' => $violation,
            ],
        ]);
    }

    public function distributorScorecard(string $uuid): JsonResponse
    {
        $distributor = Distributor::query()->where('uuid', $uuid)->first();
        if (! $distributor) {
            return response()->json(['error' => 'distributor_not_found'], 404);
        }

        $snapshots = DistributorInventorySnapshot::query()
            ->where('distributor_id', $distributor->id)
            ->orderByDesc('computed_at')
            ->limit(50)
            ->get();

        $leakage = $this->inventory->leakageCount($distributor);

        return response()->json([
            'data' => [
                'distributor_uuid' => $distributor->uuid,
                'name' => $distributor->name,
                'status' => $distributor->status,
                'type' => $distributor->type,
                'trust_score' => $distributor->trust_score,
                'leakage_count' => $leakage,
                'snapshots' => $snapshots->map(fn ($s): array => [
                    'event_id' => $s->event_id,
                    'on_hand_count' => $s->on_hand_count,
                    'sold_count' => $s->sold_count,
                    'voided_count' => $s->voided_count,
                    'gross_revenue_cents' => $s->gross_revenue_cents,
                    'currency' => $s->currency,
                    'computed_at' => $s->computed_at?->toIso8601String(),
                ])->all(),
            ],
        ]);
    }
}
