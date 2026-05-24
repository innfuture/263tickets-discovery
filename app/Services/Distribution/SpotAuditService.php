<?php

declare(strict_types=1);

namespace App\Services\Distribution;

use App\Models\Distributor;
use App\Models\DistributorSpotAudit;
use App\Models\OfflineTicket;
use App\Models\TicketCustodyLedgerEntry;
use Carbon\CarbonImmutable;

/**
 * Issues + grades probabilistic spot-audit challenges. Random
 * selection from the distributor's currently-on-hand ticket set
 * (latest ledger state == received); response is a photo of each
 * challenge UUID with a date code overlay.
 *
 * Pass → spot_audit_ok ledger entry per challenge ticket + trust
 * score bump.
 * Fail / expired → spot_audit_failed per ticket + score penalty +
 * dispatch-privilege review if fail rate crosses threshold.
 */
class SpotAuditService
{
    public function __construct(
        protected TicketCustodyLedger $ledger,
    ) {}

    public function issue(Distributor $distributor): ?DistributorSpotAudit
    {
        $size = (int) config('distribution.spot_audit.tickets_per_challenge', 3);
        $hours = (int) config('distribution.spot_audit.response_window_hours', 24);

        // Pick from tickets whose latest ledger state for this distributor
        // is `received` and not yet sold/transferred/voided.
        $candidateUuids = TicketCustodyLedgerEntry::query()
            ->where('actor_type', TicketCustodyLedgerEntry::ACTOR_DISTRIBUTOR)
            ->where('actor_id', $distributor->id)
            ->where('event_type', TicketCustodyLedgerEntry::EVENT_RECEIVED)
            ->orderByDesc('id')
            ->limit(1000)
            ->pluck('ticket_uuid')
            ->unique()
            ->values();

        // Filter to those still in received state (no later state row).
        $stillOnHand = $candidateUuids->filter(function (string $uuid): bool {
            return $this->ledger->currentState($uuid) === TicketCustodyLedgerEntry::EVENT_RECEIVED;
        })->values();

        if ($stillOnHand->isEmpty()) {
            return null;
        }

        $sample = $stillOnHand->random(min($size, $stillOnHand->count()));
        $sample = $stillOnHand->count() === 1 ? [(string) $sample] : $sample->all();

        $now = CarbonImmutable::now();

        return DistributorSpotAudit::create([
            'distributor_id' => $distributor->id,
            'challenge_ticket_uuids' => $sample,
            'issued_at' => $now,
            'due_at' => $now->addHours($hours),
            'status' => DistributorSpotAudit::STATUS_PENDING,
        ]);
    }

    /**
     * Distributor's response: a map of ticket_uuid => photo_path
     * (already uploaded to storage). We don't verify the photo content
     * here — the reviewer flag toggles pass/fail. This call records
     * the structured response + transitions the audit row.
     *
     * @param  array<string, string>  $photoMap
     */
    public function recordResponse(DistributorSpotAudit $audit, array $photoMap): DistributorSpotAudit
    {
        $audit->forceFill([
            'responded_at' => CarbonImmutable::now(),
            'response_payload' => ['photos' => $photoMap],
        ])->save();

        return $audit;
    }

    public function grade(DistributorSpotAudit $audit, bool $passed, ?string $reviewerNote = null): DistributorSpotAudit
    {
        $audit->forceFill([
            'status' => $passed ? DistributorSpotAudit::STATUS_PASSED : DistributorSpotAudit::STATUS_FAILED,
            'notes' => $reviewerNote,
        ])->save();

        $eventType = $passed
            ? TicketCustodyLedgerEntry::EVENT_SPOT_AUDIT_OK
            : TicketCustodyLedgerEntry::EVENT_SPOT_AUDIT_FAILED;

        foreach ((array) $audit->challenge_ticket_uuids as $uuid) {
            $this->ledger->append(
                ticketUuid: (string) $uuid,
                eventType: $eventType,
                actorType: TicketCustodyLedgerEntry::ACTOR_BACKOFFICE_USER,
                actorId: 0,
                payload: [
                    'audit_uuid' => $audit->uuid,
                    'distributor_id' => $audit->distributor_id,
                    'reviewer_note' => $reviewerNote,
                ],
            );
        }

        return $audit;
    }
}
