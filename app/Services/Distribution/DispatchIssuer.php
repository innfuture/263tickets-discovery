<?php

declare(strict_types=1);

namespace App\Services\Distribution;

use App\Models\Distributor;
use App\Models\OfflineTicket;
use App\Models\TicketCustodyLedgerEntry;
use App\Models\TicketDispatch;
use App\Models\TicketDispatchItem;
use App\Services\Distribution\Exceptions\DispatchException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Issues a dispatch from the organization (or a parent distributor)
 * to a recipient distributor. Builds the Merkle root, signs the
 * manifest, persists the dispatch + items rows, and writes a
 * `dispatched` ledger entry per ticket.
 *
 * Concurrency: items are locked FOR UPDATE during issuance to prevent
 * the same ticket being dispatched twice in parallel.
 */
class DispatchIssuer
{
    public function __construct(
        protected TicketCustodyLedger $ledger,
        protected MerkleTree $merkle,
        protected ManifestSigner $signer,
        protected TicketLifecyclePolicy $policy,
    ) {}

    /**
     * @param  array<int, int>  $offlineTicketIds
     * @param  array{seal_id?: string, photo_path?: string, photo_hash?: string, notes?: string}  $tamperEvidence
     */
    public function issue(
        string $fromActorType,
        int $fromActorId,
        Distributor $recipient,
        array $offlineTicketIds,
        ?int $eventId = null,
        array $tamperEvidence = [],
    ): TicketDispatch {
        if ($offlineTicketIds === []) {
            throw new DispatchException('empty_manifest', 'Cannot issue a dispatch with zero tickets.');
        }

        $cap = (int) config('distribution.dispatch.max_tickets_per_dispatch', 100000);
        if (count($offlineTicketIds) > $cap) {
            throw new DispatchException(
                'manifest_too_large',
                "Dispatch carries ".count($offlineTicketIds)." tickets; cap is {$cap}.",
            );
        }

        if (! in_array($recipient->status, [Distributor::STATUS_ACTIVE], true)) {
            throw new DispatchException(
                'recipient_not_active',
                "Recipient {$recipient->uuid} is in status {$recipient->status}; cannot receive dispatches.",
            );
        }

        return DB::transaction(function () use ($fromActorType, $fromActorId, $recipient, $offlineTicketIds, $eventId, $tamperEvidence): TicketDispatch {
            // Lock and load the tickets in one shot.
            $tickets = OfflineTicket::query()
                ->whereIn('id', $offlineTicketIds)
                ->lockForUpdate()
                ->get();

            if ($tickets->count() !== count($offlineTicketIds)) {
                throw new DispatchException(
                    'tickets_not_found',
                    'Some of the requested ticket IDs do not exist.',
                );
            }

            // Validate each ticket's state allows a dispatch.
            $uuids = [];
            foreach ($tickets as $ticket) {
                $state = $this->ledger->currentState((string) $ticket->uuid);
                if (! $this->policy->canTransition($state, TicketCustodyLedgerEntry::EVENT_DISPATCHED)) {
                    throw new DispatchException(
                        'ticket_state_invalid',
                        "Ticket {$ticket->uuid} is in state {$state}; cannot dispatch.",
                    );
                }
                $uuids[] = (string) $ticket->uuid;
            }

            // Build Merkle root over the SORTED, UNIQUE uuid list.
            $tree = $this->merkle->build($uuids);
            $merkleRoot = $tree['root'];
            $paths = $tree['paths'];

            $signature = $this->signer->sign($merkleRoot, (string) $recipient->uuid, $eventId);
            $now = CarbonImmutable::now();

            $dispatch = TicketDispatch::create([
                'from_actor_type' => $fromActorType,
                'from_actor_id' => $fromActorId,
                'to_distributor_id' => $recipient->id,
                'event_id' => $eventId,
                'ticket_count' => count($uuids),
                'merkle_root' => $merkleRoot,
                'status' => TicketDispatch::STATUS_ISSUED,
                'issued_at' => $now,
                'tamper_evidence_seal_id' => $tamperEvidence['seal_id'] ?? null,
                'tamper_evidence_photo_path' => $tamperEvidence['photo_path'] ?? null,
                'tamper_evidence_photo_hash' => $tamperEvidence['photo_hash'] ?? null,
                'manifest_signature' => $signature,
                'signing_key_id' => $this->signer->keyId(),
                'notes' => $tamperEvidence['notes'] ?? null,
            ]);

            // Persist items + write the `dispatched` ledger entry per
            // ticket. Insert items in bulk for big dispatches.
            $itemsBulk = [];
            foreach ($tickets as $ticket) {
                $uuid = (string) $ticket->uuid;
                $itemsBulk[] = [
                    'dispatch_id' => $dispatch->id,
                    'offline_ticket_id' => $ticket->id,
                    'ticket_uuid' => $uuid,
                    'merkle_leaf_hash' => $this->merkle->leafHash($uuid),
                    'merkle_path' => json_encode($paths[$uuid] ?? []),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            TicketDispatchItem::insert($itemsBulk);

            foreach ($tickets as $ticket) {
                $this->ledger->append(
                    ticketUuid: (string) $ticket->uuid,
                    eventType: TicketCustodyLedgerEntry::EVENT_DISPATCHED,
                    actorType: $fromActorType === TicketDispatch::FROM_ORGANIZATION
                        ? TicketCustodyLedgerEntry::ACTOR_ORGANIZATION
                        : TicketCustodyLedgerEntry::ACTOR_DISTRIBUTOR,
                    actorId: $fromActorId,
                    payload: [
                        'dispatch_uuid' => $dispatch->uuid,
                        'recipient_distributor_uuid' => $recipient->uuid,
                        'merkle_root' => $merkleRoot,
                        'event_id' => $eventId,
                    ],
                    signature: $signature,
                    signingKeyId: $this->signer->keyId(),
                    occurredAt: $now,
                );
            }

            return $dispatch->fresh(['items']);
        });
    }
}
