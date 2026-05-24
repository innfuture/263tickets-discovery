<?php

declare(strict_types=1);

namespace App\Jobs\Analytics;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Services\Analytics\SelloutPredictor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Daily sweep. Computes sellout predictions for every upcoming,
 * non-sold-out event. Cheap — linear-velocity is O(n) over the
 * last N days of orders per event.
 */
class ComputeSelloutPredictionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SelloutPredictor $predictor): void
    {
        Event::query()
            ->where('status', EventStatus::Published->value)
            ->where('starts_at', '>=', now())
            ->whereNotNull('capacity')
            ->orderBy('id')
            ->chunkById(200, function ($events) use ($predictor) {
                foreach ($events as $event) {
                    $predictor->predictFor($event);
                }
            });
    }
}
