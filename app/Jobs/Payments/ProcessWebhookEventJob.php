<?php

declare(strict_types=1);

namespace App\Jobs\Payments;

use App\Enums\PaymentStatus;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies the side effects of a verified webhook to our domain. Runs
 * out-of-band so the webhook endpoint can respond 200 immediately and
 * stop the provider's retry loop.
 *
 * Effects today:
 *   - Update the matching PaymentTransaction's status / settled_at /
 *     failed_at and persist the raw payload.
 *   - Mark the PaymentWebhookEvent row processed (or failed).
 *
 * Downstream concerns (notifying the order, sending receipts) should
 * be triggered by listening to the model's saved/updated events
 * elsewhere — keeping the job thin.
 */
class ProcessWebhookEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $backoff = 30;

    public function __construct(public int $webhookEventId) {}

    public function handle(): void
    {
        $event = PaymentWebhookEvent::find($this->webhookEventId);
        if ($event === null || $event->processing_status === 'processed') {
            return;
        }

        try {
            $transaction = $this->resolveTransaction($event);

            if ($transaction === null) {
                $event->update([
                    'processing_status' => 'skipped',
                    'processing_error' => 'No matching PaymentTransaction.',
                    'processed_at' => now(),
                ]);

                return;
            }

            DB::transaction(function () use ($event, $transaction) {
                $status = PaymentStatus::tryFrom($event->status) ?? PaymentStatus::PENDING;
                $transaction->markStatus($status, $event->payload);

                $event->update([
                    'processing_status' => 'processed',
                    'processed_at' => now(),
                ]);
            });
        } catch (Throwable $e) {
            $event->update([
                'processing_status' => 'failed',
                'processing_error' => $e->getMessage(),
            ]);

            Log::error('payment.webhook.processing_failed', [
                'event_id' => $event->id,
                'gateway' => $event->gateway,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function resolveTransaction(PaymentWebhookEvent $event): ?PaymentTransaction
    {
        $query = PaymentTransaction::where('gateway', $event->gateway);

        if (! empty($event->reference)) {
            $found = (clone $query)->where('reference', $event->reference)->first();
            if ($found !== null) {
                return $found;
            }
        }

        if (! empty($event->gateway_reference)) {
            return (clone $query)
                ->where('gateway_reference', $event->gateway_reference)
                ->first();
        }

        return null;
    }
}
