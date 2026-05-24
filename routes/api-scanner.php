<?php

use App\Http\Controllers\Api\Scanning\AnalyticsController;
use App\Http\Controllers\Api\Scanning\HeartbeatController;
use App\Http\Controllers\Api\Scanning\MeController;
use App\Http\Controllers\Api\Scanning\PairingController;
use App\Http\Controllers\Api\Scanning\ScanController;
use App\Http\Controllers\Api\Scanning\TicketLookupController;
use App\Http\Middleware\ScannerTokenAuth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public scanner API
|--------------------------------------------------------------------------
|
| Versioned, stateless, Bearer-token authenticated. Designed for any
| third-party mobile / desktop / kiosk scanning app. CSRF is exempt
| via the bootstrap middleware closure.
|
| Two auth strata:
|
|   /api/v1/scanning/pair    no Bearer required — exchanges a one-time
|                            pairing code (issued from the web admin)
|                            for a long-lived device token.
|
|   /api/v1/scanning/*       Bearer token required (ScannerTokenAuth):
|                            verifies the token, enforces IP allowlist
|                            and per-device rate limit, updates the
|                            device's last_seen telemetry, and binds
|                            the ScannerDevice + ScannerProfile into
|                            the request for controllers to read.
*/

Route::prefix('api/v1/scanning')->name('api.scanning.')->group(function (): void {
    Route::post('pair', PairingController::class)->name('pair');

    Route::middleware(ScannerTokenAuth::class)->group(function (): void {
        Route::get('me', MeController::class)->name('me');

        Route::post('heartbeat', HeartbeatController::class)->name('heartbeat');

        Route::post('scan', [ScanController::class, 'single'])->name('scan');
        Route::post('scan/batch', [ScanController::class, 'batch'])->name('scan.batch');

        Route::get('tickets/{payload}', TicketLookupController::class)
            ->where('payload', '.*')
            ->name('tickets.lookup');

        Route::get('events/{event:slug}/analytics', [AnalyticsController::class, 'eventAnalytics'])
            ->name('events.analytics');

        Route::get('recent', [AnalyticsController::class, 'recentScans'])->name('recent');
    });
});
