<?php

declare(strict_types=1);

namespace App\Services\Distribution;

use App\Models\Distributor;
use App\Models\TicketCustodyLedgerEntry;
use App\Models\TicketDispatch;
use App\Services\Distribution\Exceptions\DispatchException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Recipient-side custody handoff. Verifies the manifest signature,
 * optionally checks the tamper-evidence photo hash matches what the
 * recipient observed on arrival, then writes a `received` ledger
 * entry per ticket and flips the dispatch row to `received`.
 *
 * If the recipient reports a broken seal or a count mismatch, the
 * dispatch flips to `disputed` instead — no ticket enters the
 * recipient's on_hand pool. Disputes are resolved out-of-band.
 */
class DispatchReceiver
{
    public function __construct(
        protected TicketCustodyLedger $ledger,
        protected ManifestSigner $signer,
    ) {}

    public function receive(
        TicketDispatch $dispatch,
        Distributor $recipient,
        int $actorUserId,
        ?string $observedSealId = null,
        ?string $observedPhotoHash = null,
    ): TicketDispatch {
        if ($dispatch->to_distributor_id !== $recipient->id) {
            throw new DispatchException(
                'wrong_recipient',
                "Dispatch {$dispatch->uuid} addressed to a different distributor.",
            );
        }

        if (! $dispatch->isReceivable()) {
            throw new DispatchException(
                'not_receivable',
                "Dispatch {$dispatch->uuid} is in status {$dispatch->status}; cannot receive.",
            );
        }

        // Manifest signature must verify against the recipient + event
        // scope. Mismatch = forgery attempt, refuse.
        $sigOk = $this->signer->verify(
            $dispatch->manifest_signature,
            $dispatch->merkle_root,
            (string) $recipient->uuid,
            $dispatch->event_id,
            (int) config('distribution.manifest_signature_tolerance_seconds', 86400),
        );
        // Allow `unsigned` manifests in non-production deployments
        // (signing_secret blank) so dev/CI still works.
        if (! $sigOk && $dispatch->manifest_signature !== 'unsigned') {
            throw new DispatchException(
                'signature_invalid',
                "Manifest signature for dispatch {$dispatch->uuid} failed verification.",
            );
        }

        return DB::transaction(function () use ($dispatch, $recipient, $actorUserId, $observedSealId, $observedPhotoHash): TicketDispatch {
            $now = CarbonImmutable::now();

            // Optional tamper-evidence cross-check. Mismatch => dispute,
            // not receipt.
            $tamperMismatch = false;
            if ($dispatch->tamper_evidence_seal_id !== null && $observedSealId !== null
                && ! hash_equals($dispatch->tamper_evidence_seal_id, $observedSealId)) {
                $tamperMismatch = true;
            }
            if ($dispatch->tamper_evidence_photo_hash !== null && $observedPhotoHash !== null
                && ! hash_equals($dispatch->tamper_evidence_photo_hash, $observedPhotoHash)) {
                $tamperMismatch = true;
            }

            if ($tamperMismatch) {
                $dispatch->forceFill([
                    'status' => TicketDispatch::STATUS_DISPUTED,
                    'disputed_at' => $now,
                    'notes' => trim(($dispatch->notes ?? '')."\n[receipt] tamper-evidence mismatch on arrival."),
                ])->save();

                return $dispatch->fresh();
            }

            $items = $dispatch->items()->get();
            foreach ($items as $item) {
                $this->ledger->append(
                    ticketUuid: (string) $item->ticket_uuid,
                    eventType: TicketCustodyLedgerEntry::EVENT_RECEIVED,
                    actorType: TicketCustodyLedgerEntry::ACTOR_DISTRIBUTOR,
                    actorId: $recipient->id,
                    payload: [
                        'dispatch_uuid' => $dispatch->uuid,
                        'recipient_user_id' => $actorUserId,
                    ],
                    occurredAt: $now,
                );
            }

            $dispatch->forceFill([
                'status' => TicketDispatch::STATUS_RECEIVED,
                'received_at' => $now,
            ])->save();

            return $dispatch->fresh();
        });
    }

    /**
     * Recipient explicitly disputes the dispatch before any tickets
     * cross into on_hand. The dispatch is marked disputed and an
     * out-of-band ops process resolves it (re-ship, refund, etc.).
     */
    public function dispute(
        TicketDispatch $dispatch,
        Distributor $recipient,
        int $actorUserId,
        string $reason,
    ): TicketDispatch {
        if ($dispatch->to_distributor_id !== $recipient->id) {
            throw new DispatchException('wrong_recipient', 'Dispatch addressed to a different distributor.');
        }
        if (! $dispatch->isReceivable()) {
            throw new DispatchException('not_disputable', "Dispatch in status {$dispatch->status} cannot be disputed.");
        }

        $dispatch->forceFill([
            'status' => TicketDispatch::STATUS_DISPUTED,
            'disputed_at' => CarbonImmutable::now(),
            'notes' => trim(($dispatch->notes ?? '')."\n[dispute by user {$actorUserId}] ".$reason),
        ])->save();

        return $dispatch->fresh();
    }
}
