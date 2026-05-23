<?php

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
