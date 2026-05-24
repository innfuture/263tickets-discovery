<?php

declare(strict_types=1);

namespace App\Jobs\Automation;

use App\Models\Organization;
use App\Models\WebhookOutboxEntry;
use App\Services\Automation\AutomationDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Drains the `webhook_outbox` table — calls the dispatcher's direct
 * `fanOut()` for each pending entry. Marks dispatched / failed via
 * row-level locks so concurrent workers don't process the same row.
 *
 * Scheduled every minute via routes/console.php; can also be
 * dispatched manually from the back-office "retry now" button.
 */
class DispatchPendingOutboxWebhooksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $batchSize = 200;

    public function handle(AutomationDispatcher $dispatcher): void
    {
        $maxAttempts = max(1, (int) config('automation.outbox_max_attempts', 6));

        WebhookOutboxEntry::query()
            ->where('status', WebhookOutboxEntry::STATUS_PENDING)
            ->where(fn ($q) => $q->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->orderBy('id')
            ->limit($this->batchSize)
            ->lockForUpdate()
            ->get()
            ->each(function (WebhookOutboxEntry $entry) use ($dispatcher, $maxAttempts) {
                $this->processOne($entry, $dispatcher, $maxAttempts);
            });
    }

    protected function processOne(
        WebhookOutboxEntry $entry,
        AutomationDispatcher $dispatcher,
        int $maxAttempts,
    ): void {
        $org = Organization::query()->find($entry->organization_id);
        if (! $org) {
            $entry->forceFill([
                'status' => WebhookOutboxEntry::STATUS_FAILED,
                'last_error' => 'organization not found',
            ])->save();

            return;
        }

        try {
            DB::transaction(function () use ($entry, $org, $dispatcher) {
                $dispatcher->fanOut(
                    eventType: (string) $entry->event_type,
                    org: $org,
                    payload: (array) $entry->payload,
                );

                $entry->forceFill([
                    'status' => WebhookOutboxEntry::STATUS_DISPATCHED,
                    'dispatched_at' => now(),
                    'attempts' => (int) $entry->attempts + 1,
                ])->save();
            });
        } catch (Throwable $e) {
            $attempts = (int) $entry->attempts + 1;
            $isFinal = $attempts >= $maxAttempts;

            $entry->forceFill([
                'status' => $isFinal ? WebhookOutboxEntry::STATUS_FAILED : WebhookOutboxEntry::STATUS_PENDING,
                'attempts' => $attempts,
                'last_error' => substr($e->getMessage(), 0, 1000),
                // Backoff schedule for retries: 1m, 5m, 30m, 5h, 24h.
                'available_at' => $isFinal
                    ? $entry->available_at
                    : now()->addSeconds([60, 300, 1800, 18000, 86400][min($attempts, 4)]),
            ])->save();

            Log::warning('automation.outbox.retry', [
                'outbox_id' => $entry->id,
                'event_type' => $entry->event_type,
                'attempts' => $attempts,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
