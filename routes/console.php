<?php

use App\Jobs\Automation\DispatchPendingOutboxWebhooksJob;
use App\Jobs\Storefront\ExpireBuyerMembershipsJob;
use App\Jobs\Storefront\ExpireStaleCheckoutSessionsJob;
use App\Jobs\Storefront\ExpireStaleSeatHoldsJob;
use App\Jobs\Storefront\GenerateRecurringEventInstancesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reconcile in-flight payments against their upstream gateways. Catches
// missed webhooks and gives every PENDING row a fresh status read on a
// fixed cadence.
Schedule::command('payments:poll-pending')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Sandbox housekeeping — only meaningful when the sandbox is on, but
// safe to schedule unconditionally (commands no-op on empty tables).
Schedule::command('sandbox:flush-webhooks')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('sandbox:prune')
    ->dailyAt('03:30')
    ->onOneServer();

Schedule::command('sandbox:resolve-disputes')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// Sweep expired checkout sessions every minute so inventory holds are
// released promptly once a buyer abandons the cart. Aligned with the
// 15-minute hold TTL — sub-minute precision isn't worth the cron noise.
Schedule::job(new ExpireStaleCheckoutSessionsJob)
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// Same sweep for the per-seat holds (reserved seating). Unique-on-
// seat_id means a stranded expired hold blocks new buyers; the
// session expire job above doesn't touch seat_holds directly.
Schedule::job(new ExpireStaleSeatHoldsJob)
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// Recurring events — clone source Events per template cadence.
// Hourly is the practical floor; sub-hourly cadences (e.g. half-hourly
// museum slots) would need everyFiveMinutes().
Schedule::job(new GenerateRecurringEventInstancesJob)
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// Buyer memberships — flip expired rows. Daily is fine; renewal
// reminder emails are a separate job (out of engine scope).
Schedule::job(new ExpireBuyerMembershipsJob)
    ->dailyAt('02:00')
    ->onOneServer();

// Transactional outbox drain — fans out queued domain events to
// subscribed organization_webhooks. Every minute is the right cadence
// — outbox row pickup is sub-second, drain latency is dominated by
// the per-webhook HTTP timeout (10s default).
Schedule::job(new DispatchPendingOutboxWebhooksJob)
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();
