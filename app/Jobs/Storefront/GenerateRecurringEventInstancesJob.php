<?php

declare(strict_types=1);

namespace App\Jobs\Storefront;

use App\Services\Storefront\RecurringEventGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Scheduled hourly. Walks every active event_template whose
 * `next_run_at` has come due and clones the source event into a new
 * instance. The generator handles slug / UUID rotation + inventory
 * reset.
 */
class GenerateRecurringEventInstancesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(RecurringEventGenerator $generator): void
    {
        $generator->generateDue();
    }
}
