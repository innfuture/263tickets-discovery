<?php

namespace App\Jobs;

use App\Actions\Tickets\GenerateOfflineTicketsAction;
use App\Enums\TicketGenerationStatus;
use App\Models\TicketCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateOfflineTicketsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public int $backoff = 60;

    public function __construct(
        public readonly TicketCategory $category,
    ) {}

    public function handle(GenerateOfflineTicketsAction $action): void
    {
        $action->execute($this->category);
    }

    public function failed(\Throwable $e): void
    {
        $this->category->update([
            'generation_status' => TicketGenerationStatus::Failed,
            'generation_progress' => null,
        ]);
    }
}
