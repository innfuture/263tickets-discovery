<?php

declare(strict_types=1);

namespace App\Jobs\Payments;

use App\Enums\PaymentStatus;
use App\Models\PaymentTransaction;
use App\Services\Payments\Contracts\PollsTransactionStatus;
use App\Services\Payments\PaymentManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pull-side reconciliation for a single transaction. Dispatched by:
 *
 *   • StatusController, when an SPA poll hits a stale PENDING row.
 *   • PollPendingPaymentsCommand, on a schedule, for the long tail.
 *
 * Driver is asked for `status()`; the returned PaymentStatus is
 * written back. If the driver does not implement PollsTransactionStatus
 * the job is a no-op (some providers are webhook-only).
 */
class ReconcilePaymentStatusJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $transactionId) {}

    public function handle(PaymentManager $payments): void
    {
        $transaction = PaymentTransaction::find($this->transactionId);
        if ($transaction === null) {
            return;
        }

        $status = PaymentStatus::tryFrom($transaction->status);
        if ($status !== null && $status->isTerminal()) {
            return;
        }

        try {
            $gateway = $payments->gateway($transaction->gateway);
        } catch (Throwable) {
            return;
        }

        if (! $gateway instanceof PollsTransactionStatus) {
            return;
        }

        try {
            $result = $gateway->status($transaction->reference, $transaction->gateway_reference);
        } catch (Throwable $e) {
            $transaction->reconcile_attempts = $transaction->reconcile_attempts + 1;
            $transaction->last_reconciled_at = now();
            $transaction->save();

            Log::warning('payment.reconcile.failed', [
                'transaction_id' => $transaction->id,
                'gateway' => $transaction->gateway,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        if ($result->gatewayReference !== null && $result->gatewayReference !== '') {
            $transaction->gateway_reference = $result->gatewayReference;
        }

        $transaction->reconcile_attempts = $transaction->reconcile_attempts + 1;
        $transaction->last_reconciled_at = now();
        $transaction->markStatus($result->status, $result->raw);
    }
}
