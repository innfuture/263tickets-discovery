<?php

declare(strict_types=1);

namespace App\Services\Distribution;

use App\Models\TicketCustodyLedgerEntry as Entry;
use App\Services\Distribution\Exceptions\IllegalCustodyTransition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Append-only chain-of-custody ledger writer + reader. Every
 * physical-ticket state transition flows through here.
 *
 * Per-ticket invariants:
 *   - `sequence` is monotonic, starting at 1 for the first row.
 *   - `prev_hash` is the previous row's `this_hash` (empty string for
 *     sequence 1).
 *   - `this_hash` = SHA-256(prev_hash || canonical_json(core_payload)).
 *   - `event_type` is a legal transition from the previous row's
 *     event_type per TicketLifecyclePolicy.
 *
 * Concurrency: writes for a given ticket are serialised by a row
 * lock on the previous head. Two simultaneous writes for the same
 * ticket will serialise; the loser observes the winner's row and
 * sequences after it.
 */
class TicketCustodyLedger
{
    public function __construct(
        protected TicketLifecyclePolicy $policy,
    ) {}

    /**
     * Append a new entry to a ticket's chain. Returns the persisted
     * entry. Throws IllegalCustodyTransition if the requested event
     * is not legal from the current state; in that case a
     * `lifecycle_violation` audit entry is also written so the
     * attempt is forensically visible.
     *
     * @param  array<string, mixed>  $payload
     */
    public function append(
        string $ticketUuid,
        string $eventType,
        string $actorType,
        int $actorId,
        array $payload,
        ?string $signature = null,
        ?string $signingKeyId = null,
        ?CarbonImmutable $occurredAt = null,
    ): Entry {
        return DB::transaction(function () use (
            $ticketUuid, $eventType, $actorType, $actorId,
            $payload, $signature, $signingKeyId, $occurredAt,
        ): Entry {
            $previous = Entry::query()
                ->where('ticket_uuid', $ticketUuid)
                ->orderByDesc('sequence')
                ->lockForUpdate()
                ->first();

            $fromState = $previous?->event_type ?? Entry::EVENT_PRINTED;

            // Special case: the first event for a ticket is allowed to
            // be `printed` (the implicit initial state). Otherwise the
            // policy decides.
            $isFirstPrintRow = $previous === null && $eventType === Entry::EVENT_PRINTED;

            if (! $isFirstPrintRow && ! $this->policy->canTransition($fromState, $eventType)) {
                $this->writeViolation($ticketUuid, $previous, $eventType, $actorType, $actorId);
                throw new IllegalCustodyTransition($ticketUuid, $fromState, $eventType);
            }

            $sequence = ($previous?->sequence ?? 0) + 1;
            $prevHash = $previous?->this_hash ?? '';
            $occurredAt ??= CarbonImmutable::now();

            $core = [
                'ticket_uuid' => $ticketUuid,
                'sequence' => $sequence,
                'event_type' => $eventType,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'payload' => $payload,
                'occurred_at' => $occurredAt->toIso8601String(),
            ];
            $thisHash = $this->computeHash($prevHash, $core);

            return Entry::create([
                'ticket_uuid' => $ticketUuid,
                'sequence' => $sequence,
                'event_type' => $eventType,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'payload' => $payload,
                'prev_hash' => $prevHash,
                'this_hash' => $thisHash,
                'signature' => $signature,
                'signing_key_id' => $signingKeyId,
                'occurred_at' => $occurredAt,
                'recorded_at' => CarbonImmutable::now(),
            ]);
        });
    }

    /**
     * The current state of a ticket = the event_type of its latest
     * non-audit row, or `printed` if no row exists yet.
     */
    public function currentState(string $ticketUuid): string
    {
        $latest = Entry::query()
            ->where('ticket_uuid', $ticketUuid)
            ->whereNotIn('event_type', [
                Entry::EVENT_SPOT_AUDIT_OK,
                Entry::EVENT_SPOT_AUDIT_FAILED,
                Entry::EVENT_GEO_ANOMALY,
                Entry::EVENT_LIFECYCLE_VIOLATION,
            ])
            ->orderByDesc('sequence')
            ->first();

        return $latest?->event_type ?? Entry::EVENT_PRINTED;
    }

    /**
     * Walk a ticket's full chain in order. Use this for forensics +
     * the per-ticket lifecycle viewer in the backoffice dashboard.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Entry>
     */
    public function history(string $ticketUuid)
    {
        return Entry::query()
            ->where('ticket_uuid', $ticketUuid)
            ->orderBy('sequence')
            ->get();
    }

    /**
     * Verify the chain is intact for a ticket: every prev_hash must
     * equal the previous row's this_hash, and every this_hash must
     * recompute from its payload. Returns null on success or a
     * description of the first violation.
     */
    public function verifyChain(string $ticketUuid): ?string
    {
        $entries = $this->history($ticketUuid);
        $expectedPrev = '';

        foreach ($entries as $entry) {
            if ($entry->prev_hash !== $expectedPrev) {
                return "Chain break at sequence {$entry->sequence}: prev_hash mismatch.";
            }

            $core = [
                'ticket_uuid' => $entry->ticket_uuid,
                'sequence' => $entry->sequence,
                'event_type' => $entry->event_type,
                'actor_type' => $entry->actor_type,
                'actor_id' => $entry->actor_id,
                'payload' => $entry->payload,
                'occurred_at' => $entry->occurred_at?->toIso8601String(),
            ];

            $recomputed = $this->computeHash($entry->prev_hash, $core);
            if ($recomputed !== $entry->this_hash) {
                return "Chain break at sequence {$entry->sequence}: this_hash mismatch (tampered payload?).";
            }

            $expectedPrev = $entry->this_hash;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $core
     */
    protected function computeHash(string $prevHash, array $core): string
    {
        $canonical = json_encode($core, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $prevHash.'|'.$canonical);
    }

    protected function writeViolation(
        string $ticketUuid,
        ?Entry $previous,
        string $attemptedEvent,
        string $actorType,
        int $actorId,
    ): void {
        $sequence = ($previous?->sequence ?? 0) + 1;
        $prevHash = $previous?->this_hash ?? '';
        $payload = [
            'attempted_event' => $attemptedEvent,
            'previous_event' => $previous?->event_type,
            'previous_sequence' => $previous?->sequence,
        ];
        $core = [
            'ticket_uuid' => $ticketUuid,
            'sequence' => $sequence,
            'event_type' => Entry::EVENT_LIFECYCLE_VIOLATION,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'payload' => $payload,
            'occurred_at' => CarbonImmutable::now()->toIso8601String(),
        ];

        Entry::create([
            'ticket_uuid' => $ticketUuid,
            'sequence' => $sequence,
            'event_type' => Entry::EVENT_LIFECYCLE_VIOLATION,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'payload' => $payload,
            'prev_hash' => $prevHash,
            'this_hash' => $this->computeHash($prevHash, $core),
            'occurred_at' => CarbonImmutable::now(),
            'recorded_at' => CarbonImmutable::now(),
        ]);
    }
}
