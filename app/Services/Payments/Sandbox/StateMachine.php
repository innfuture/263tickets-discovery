<?php

declare(strict_types=1);

namespace App\Services\Payments\Sandbox;

use App\Enums\SandboxState;
use App\Models\SandboxStateLog;
use App\Models\SandboxTransaction;
use App\Services\Payments\Sandbox\Exceptions\IllegalTransitionException;
use Illuminate\Support\Facades\DB;

/**
 * Canonical state machine (§3.1). Hard-coded transition table — every
 * move not listed is rejected with IllegalTransitionException.
 *
 * Every transition writes a SandboxStateLog entry; the dashboard
 * lifecycle visualisation (§4.3) is rendered from that log.
 */
class StateMachine
{
    /**
     * Allowed moves. Read as: `key` may move to any state in `value`.
     *
     * @var array<string, array<int, SandboxState>>
     */
    protected const TRANSITIONS = [
        'initiated' => [
            SandboxState::AUTH_PENDING,
            SandboxState::AUTHORIZED,
            SandboxState::CAPTURED,
            SandboxState::FAILED,
        ],
        'auth_pending' => [
            SandboxState::AUTHORIZED,
            SandboxState::CAPTURED,
            SandboxState::FAILED,
        ],
        'authorized' => [
            SandboxState::CAPTURED,
            SandboxState::VOIDED,
            SandboxState::FAILED,
        ],
        'captured' => [
            SandboxState::PART_REFUNDED,
            SandboxState::REFUNDED,
            SandboxState::DISPUTED,
            SandboxState::FAILED,
        ],
        'part_refunded' => [
            SandboxState::PART_REFUNDED,
            SandboxState::REFUNDED,
            SandboxState::DISPUTED,
        ],
        'disputed' => [
            SandboxState::DISPUTE_WON,
            SandboxState::DISPUTE_LOST,
        ],
        // Terminal — no further moves
        'refunded' => [],
        'voided' => [],
        'failed' => [],
        'dispute_won' => [],
        'dispute_lost' => [],
    ];

    public function __construct(protected SandboxClock $clock) {}

    public function canTransition(SandboxState $from, SandboxState $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from->value] ?? [], true);
    }

    /**
     * Applies a transition atomically. Row-level lock prevents two
     * concurrent operations (e.g. capture + void) both succeeding.
     *
     * @param  array<string, mixed>  $context  written to the audit row
     */
    public function transition(
        SandboxTransaction $transaction,
        SandboxState $to,
        string $actor = 'system',
        ?string $reason = null,
        array $context = [],
    ): SandboxTransaction {
        return DB::transaction(function () use ($transaction, $to, $actor, $reason, $context) {
            /** @var SandboxTransaction $fresh */
            $fresh = SandboxTransaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            $from = $fresh->stateEnum;

            if ($from === $to) {
                return $fresh; // idempotent re-application
            }

            if (! $this->canTransition($from, $to)) {
                throw new IllegalTransitionException($from, $to, $reason);
            }

            $now = $this->clock->now($fresh->merchant);
            $wall = now();

            $fresh->state = $to->value;
            $this->stampTerminalTimestamps($fresh, $to, $now);
            $fresh->save();

            SandboxStateLog::create([
                'sandbox_transaction_id' => $fresh->id,
                'from_state' => $from->value,
                'to_state' => $to->value,
                'reason' => $reason,
                'actor' => $actor,
                'context' => $context,
                'occurred_at' => $wall,
                'virtual_time_at' => $now,
            ]);

            return $fresh->refresh();
        });
    }

    protected function stampTerminalTimestamps(SandboxTransaction $row, SandboxState $to, \DateTimeInterface $now): void
    {
        $field = match ($to) {
            SandboxState::AUTHORIZED => 'authorized_at',
            SandboxState::CAPTURED => 'captured_at',
            SandboxState::VOIDED => 'voided_at',
            SandboxState::REFUNDED, SandboxState::PART_REFUNDED => 'refunded_at',
            SandboxState::FAILED => 'failed_at',
            default => null,
        };

        if ($field !== null && $row->{$field} === null) {
            $row->{$field} = $now;
        }
    }
}
