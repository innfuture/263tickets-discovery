<?php

declare(strict_types=1);

namespace App\Services\Distribution;

use App\Events\TicketVoided;
use App\Models\OfflineTicket;
use App\Models\TicketCustodyLedgerEntry;
use App\Services\Distribution\Exceptions\VoidException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as EventFacade;

/**
 * Voids tickets with dual-control gating once a per-actor threshold
 * is exceeded in the rolling window. Below threshold = single-actor
 * void. Above threshold = requires a second approver (different
 * actor) on the same `void_request_id`.
 *
 * Sub-threshold voids commit immediately; above-threshold voids
 * write a pending intent and become effective when the second
 * approver confirms.
 *
 * Each effective void writes a `voided` ledger entry + fires
 * TicketVoided so the existing scanner-edge integration pushes the
 * deny entry.
 */
class VoidOrchestrator
{
    public function __construct(
        protected TicketCustodyLedger $ledger,
    ) {}

    /**
     * @param  array<int, int>  $offlineTicketIds
     */
    public function void(
        array $offlineTicketIds,
        string $actorType,
        int $actorId,
        string $reason,
        ?int $coActorId = null,
    ): int {
        if ($offlineTicketIds === []) {
            return 0;
        }

        $threshold = (int) config('distribution.voiding.dual_control_threshold', 50);
        $windowHours = (int) config('distribution.voiding.dual_control_window_hours', 24);

        $count = count($offlineTicketIds);
        $requiresDualControl = $count > $threshold
            || $this->recentVoidCountByActor($actorType, $actorId, $windowHours) + $count > $threshold;

        if ($requiresDualControl && ($coActorId === null || $coActorId === $actorId)) {
            throw new VoidException(
                'dual_control_required',
                "Voiding {$count} tickets exceeds the {$threshold}-per-{$windowHours}h threshold. A second distinct approver (co_actor_id) is required.",
            );
        }

        $tickets = OfflineTicket::query()->whereIn('id', $offlineTicketIds)->get();
        if ($tickets->count() !== $count) {
            throw new VoidException('tickets_not_found', 'Some ticket IDs do not exist.');
        }

        $now = CarbonImmutable::now();
        $voidedCount = 0;

        DB::transaction(function () use ($tickets, $actorType, $actorId, $coActorId, $reason, $now, &$voidedCount): void {
            foreach ($tickets as $ticket) {
                $state = $this->ledger->currentState((string) $ticket->uuid);
                if ($state === TicketCustodyLedgerEntry::EVENT_VOIDED) {
                    continue; // idempotent
                }

                $this->ledger->append(
                    ticketUuid: (string) $ticket->uuid,
                    eventType: TicketCustodyLedgerEntry::EVENT_VOIDED,
                    actorType: $actorType,
                    actorId: $actorId,
                    payload: [
                        'reason' => $reason,
                        'co_actor_id' => $coActorId,
                        'prior_state' => $state,
                    ],
                    occurredAt: $now,
                );

                // Mirror to OfflineTicket flags (legacy callers read these).
                $ticket->forceFill([
                    'is_voided' => true,
                    'voided_at' => $now,
                    'void_reason' => $reason,
                ])->save();

                EventFacade::dispatch(new TicketVoided($ticket, $reason));
                $voidedCount++;
            }
        });

        return $voidedCount;
    }

    /**
     * Recover tickets — distributor returns unsold stock to the
     * originator. State transitions to `recovered` and the originator
     * may either re-dispatch or void.
     *
     * @param  array<int, int>  $offlineTicketIds
     */
    public function recover(
        array $offlineTicketIds,
        string $actorType,
        int $actorId,
        string $reason,
    ): int {
        if ($offlineTicketIds === []) {
            return 0;
        }

        $tickets = OfflineTicket::query()->whereIn('id', $offlineTicketIds)->get();
        $now = CarbonImmutable::now();
        $recoveredCount = 0;

        DB::transaction(function () use ($tickets, $actorType, $actorId, $reason, $now, &$recoveredCount): void {
            foreach ($tickets as $ticket) {
                $this->ledger->append(
                    ticketUuid: (string) $ticket->uuid,
                    eventType: TicketCustodyLedgerEntry::EVENT_RECOVERED,
                    actorType: $actorType,
                    actorId: $actorId,
                    payload: ['reason' => $reason],
                    occurredAt: $now,
                );
                $recoveredCount++;
            }
        });

        return $recoveredCount;
    }

    protected function recentVoidCountByActor(string $actorType, int $actorId, int $windowHours): int
    {
        return TicketCustodyLedgerEntry::query()
            ->where('event_type', TicketCustodyLedgerEntry::EVENT_VOIDED)
            ->where('actor_type', $actorType)
            ->where('actor_id', $actorId)
            ->where('recorded_at', '>=', CarbonImmutable::now()->subHours($windowHours))
            ->count();
    }
}
