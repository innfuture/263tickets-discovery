<?php

declare(strict_types=1);

namespace App\Console\Commands\Payments;

use App\Enums\PaymentStatus;
use App\Jobs\Payments\ReconcilePaymentStatusJob;
use App\Models\PaymentTransaction;
use Illuminate\Console\Command;

/**
 * Scheduled fallback for missed webhooks. For each PENDING transaction
 * older than the stale threshold, dispatches ReconcilePaymentStatusJob.
 * Anything still pending past the expiry threshold is marked FAILED
 * synchronously — the gateway has effectively given up.
 *
 * Register on the scheduler in routes/console.php:
 *
 *   Schedule::command('payments:poll-pending')->everyFiveMinutes();
 */
class PollPendingPaymentsCommand extends Command
{
    protected $signature = 'payments:poll-pending {--limit=200}';

    protected $description = 'Reconcile in-flight payments against their gateways.';

    public function handle(): int
    {
        $staleAfter = (int) config('payments.reconciliation.stale_after_minutes', 5);
        $expireAfter = (int) config('payments.reconciliation.expire_after_minutes', 60);
        $maxAttempts = (int) config('payments.reconciliation.max_attempts', 24);
        $limit = (int) $this->option('limit');

        // 1) Expire anything past the wall-clock cut-off.
        $expiredCount = PaymentTransaction::query()
            ->where('status', PaymentStatus::PENDING->value)
            ->where('created_at', '<=', now()->subMinutes($expireAfter))
            ->update([
                'status' => PaymentStatus::FAILED->value,
                'failed_at' => now(),
            ]);

        if ($expiredCount > 0) {
            $this->info("Expired {$expiredCount} stale pending payments.");
        }

        // 2) Reconcile anything still pending past the stale threshold.
        $candidates = PaymentTransaction::query()
            ->where('status', PaymentStatus::PENDING->value)
            ->where('created_at', '<=', now()->subMinutes($staleAfter))
            ->where('reconcile_attempts', '<', $maxAttempts)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($candidates as $transaction) {
            ReconcilePaymentStatusJob::dispatch($transaction->id);
        }

        $this->info("Dispatched {$candidates->count()} reconcile jobs.");

        return self::SUCCESS;
    }
}
