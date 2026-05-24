<?php

declare(strict_types=1);

namespace App\Jobs\Storefront;

use App\Services\Storefront\MembershipManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Daily sweep. Flips `active` memberships whose `ends_at` is in the
 * past to `expired`. Renewal reminder emails are a separate job
 * (intentionally not coupled — they should run N days BEFORE expiry).
 */
class ExpireBuyerMembershipsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(MembershipManager $manager): void
    {
        $manager->expireDue();
    }
}
