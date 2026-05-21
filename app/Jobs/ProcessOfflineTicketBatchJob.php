<?php

namespace App\Jobs;

use App\Actions\Tickets\ProcessOfflineTicketBatchAction;
use App\Enums\BatchStatus;
use App\Enums\TicketGenerationStatus;
use App\Models\OfflineTicketBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued runner for one OfflineTicketBatch — create / increase / decrease.
 *
 * Delegates the real work to ProcessOfflineTicketBatchAction so the same
 * pipeline is reachable from a synchronous test, from a worker, or from
 * the dispatchAfterResponse() path TicketService uses for the local-dev
 * "no worker required" experience.
 *
 * On terminal failure, the matched failed() hook stamps the batch row +
 * the parent category so the UI's existing polling surfaces the failure.
 */
class ProcessOfflineTicketBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public int $backoff = 60;

    public function __construct(public readonly OfflineTicketBatch $batch) {}

    public function handle(ProcessOfflineTicketBatchAction $action): void
    {
        $action->execute($this->batch);
    }

    public function failed(\Throwable $e): void
    {
        // Use updateOrFail-style raw update so we don't trip on a
        // model-event handler if the row is missing.
        $this->batch->update([
            'status' => BatchStatus::Failed,
            'error' => mb_substr($e->getMessage(), 0, 2000),
            'completed_at' => now(),
        ]);

        $this->batch->loadMissing('category');
        $this->batch->category?->update([
            'generation_status' => TicketGenerationStatus::Failed,
            'generation_progress' => null,
        ]);
    }
}
