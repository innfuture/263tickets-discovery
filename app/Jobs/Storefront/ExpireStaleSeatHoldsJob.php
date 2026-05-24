<?php

declare(strict_types=1);

namespace App\Jobs\Storefront;

use App\Models\Seat;
use App\Models\SeatHold;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Mirror of ExpireStaleCheckoutSessionsJob, but for the per-seat
 * holds. The unique constraint on seat_holds.seat_id means an
 * expired-but-not-deleted row will block new buyers indefinitely.
 */
class ExpireStaleSeatHoldsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $cutoff = Carbon::now();

        DB::transaction(function () use ($cutoff) {
            $seatIds = SeatHold::query()
                ->where('expires_at', '<', $cutoff)
                ->whereNull('order_item_id')
                ->pluck('seat_id');

            if ($seatIds->isEmpty()) {
                return;
            }

            Seat::query()
                ->whereIn('id', $seatIds)
                ->where('status', '!=', Seat::STATUS_SOLD)
                ->update(['status' => Seat::STATUS_AVAILABLE]);

            SeatHold::query()
                ->where('expires_at', '<', $cutoff)
                ->whereNull('order_item_id')
                ->delete();
        });
    }
}
