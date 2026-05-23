<?php

declare(strict_types=1);

namespace App\Console\Commands\Payments;

use App\Enums\SandboxState;
use App\Models\SandboxDispute;
use App\Services\Payments\Sandbox\BalanceLedger;
use App\Services\Payments\Sandbox\StateMachine;
use App\Services\Payments\Sandbox\WebhookDispatcher;
use Illuminate\Console\Command;

/**
 * Auto-resolution scheduler (§5, phase 5). Disputes that pass
 * `evidence_due_at` without manual resolution are decided by the
 * merchant's `rules.dispute_default_outcome` (defaults to "lost" —
 * matches production worst-case so consumers test for it).
 *
 * Each resolution:
 *   - transitions the transaction to DISPUTE_WON / DISPUTE_LOST
 *   - debits or credits the ledger
 *   - emits `charge.dispute.closed`
 *
 * Schedule: hourly, idempotent. Misses are non-fatal — only delays.
 */
class SandboxResolveDisputesCommand extends Command
{
    protected $signature = 'sandbox:resolve-disputes {--limit=100}';

    protected $description = 'Auto-resolve sandbox disputes that have passed their evidence deadline.';

    public function handle(StateMachine $machine, WebhookDispatcher $webhooks, BalanceLedger $ledger): int
    {
        $candidates = SandboxDispute::query()
            ->where('status', 'open')
            ->whereNotNull('evidence_due_at')
            ->where('evidence_due_at', '<=', now())
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No disputes due for resolution.');

            return self::SUCCESS;
        }

        foreach ($candidates as $dispute) {
            $transaction = $dispute->transaction;
            $merchant = $transaction->merchant;
            $outcome = (string) (($merchant->rules['dispute_default_outcome'] ?? null) ?: 'lost');

            $targetState = $outcome === 'won'
                ? SandboxState::DISPUTE_WON
                : SandboxState::DISPUTE_LOST;

            $machine->transition(
                transaction: $transaction,
                to: $targetState,
                actor: 'system',
                reason: "auto_resolved:{$outcome}",
            );

            if ($outcome === 'won') {
                $ledger->reverseChargeback($dispute);
            }

            $dispute->update([
                'status' => $outcome,
                'resolved_at' => now(),
            ]);

            $webhooks->enqueue(
                merchant: $merchant,
                transaction: $transaction,
                type: 'charge.dispute.closed',
                scheduledDelaySeconds: 1,
            );

            $this->info("Resolved dispute {$dispute->uuid} as {$outcome}.");
        }

        return self::SUCCESS;
    }
}
