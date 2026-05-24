<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Distribution\BackofficeForensicsController;
use App\Http\Controllers\Api\Distribution\DevicePairingController;
use App\Http\Controllers\Api\Distribution\DispatchReceiptController;
use App\Http\Controllers\Api\Distribution\DistributorSalesController;
use App\Http\Middleware\EnsureDistributorDevice;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Distribution network APIs
|--------------------------------------------------------------------------
|
|   /api/v1/distributor/pair      open — exchanges a one-time code for
|                                  a device bearer token + records the
|                                  device's attestation public key.
|
|   /api/v1/distributor/*         device bearer required; resolves to a
|                                  DistributorDevice + parent Distributor.
|
|   /backoffice/distribution/*    organisation-side admin + forensics.
|                                  Auth via the existing web session +
|                                  Spatie permission middleware.
|
*/

Route::prefix('api/v1/distributor')->group(function (): void {
    Route::middleware('throttle:storefront-checkout')
        ->post('pair', [DevicePairingController::class, 'pair'])
        ->name('distributor.pair');

    Route::middleware([EnsureDistributorDevice::class, 'throttle:distributor-device'])->group(function (): void {
        Route::post('sales', [DistributorSalesController::class, 'store'])
            ->name('distributor.sales.store');

        Route::post('dispatches/{uuid}/receive', [DispatchReceiptController::class, 'receive'])
            ->name('distributor.dispatches.receive');
    });
});

Route::prefix('backoffice/distribution')
    ->middleware(['web', 'auth'])
    ->group(function (): void {
        Route::middleware('permission:distribution.audit')->group(function (): void {
            Route::get('tickets/{uuid}/lifecycle', [BackofficeForensicsController::class, 'ticketLifecycle'])
                ->name('backoffice.distribution.tickets.lifecycle');
            Route::get('tickets/{uuid}/verify', [BackofficeForensicsController::class, 'verifyChain'])
                ->name('backoffice.distribution.tickets.verify');
            Route::get('distributors/{uuid}/scorecard', [BackofficeForensicsController::class, 'distributorScorecard'])
                ->name('backoffice.distribution.distributors.scorecard');
        });
    });
