<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Buyer\BuyerAuthController;
use App\Http\Controllers\Api\Buyer\BuyerDashboardController;
use App\Http\Controllers\Api\Buyer\BuyerSelfServiceController;
use App\Http\Middleware\EnsureBuyerAuth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Buyer (authenticated public) API
|--------------------------------------------------------------------------
|
| Magic-link based. Unauthenticated request-link + verify; everything
| else gated by EnsureBuyerAuth.
|
| Rate limits reuse the public storefront pool — buyers and guests
| share the same per-IP limiters.
|
*/

Route::prefix('api/v1/buyer')->group(function () {

    // ── Auth ─────────────────────────────────────────────────────────
    Route::middleware('throttle:storefront-lookup')->group(function () {
        Route::post('auth/request-link', [BuyerAuthController::class, 'requestLink'])
            ->name('buyer.auth.request_link');
        Route::post('auth/verify', [BuyerAuthController::class, 'verify'])
            ->name('buyer.auth.verify');
    });

    // ── Authed surface ───────────────────────────────────────────────
    Route::middleware(['throttle:storefront-discovery', EnsureBuyerAuth::class])->group(function () {
        Route::post('auth/logout', [BuyerAuthController::class, 'logout']);
        Route::post('auth/logout-all', [BuyerAuthController::class, 'logoutAll']);

        Route::get('me', [BuyerDashboardController::class, 'me'])->name('buyer.me');
        Route::patch('me', [BuyerDashboardController::class, 'updateMe']);

        Route::get('upcoming', [BuyerDashboardController::class, 'upcoming']);
        Route::get('past', [BuyerDashboardController::class, 'past']);
        Route::get('orders', [BuyerDashboardController::class, 'orders']);
        Route::get('orders/{reference}', [BuyerDashboardController::class, 'order']);

        Route::get('favorites', [BuyerDashboardController::class, 'favorites']);
        Route::post('favorites/{slug}', [BuyerDashboardController::class, 'addFavorite']);
        Route::delete('favorites/{slug}', [BuyerDashboardController::class, 'removeFavorite']);

        Route::get('notifications', [BuyerDashboardController::class, 'notifications']);
        Route::post('notifications/{uuid}/read', [BuyerDashboardController::class, 'readNotification']);
        Route::post('notifications/read-all', [BuyerDashboardController::class, 'readAllNotifications']);

        Route::get('recommendations', [BuyerDashboardController::class, 'recommendations']);

        // Self-service ticket management
        Route::post('orders/{reference}/items/{item}/upgrade', [BuyerSelfServiceController::class, 'upgrade']);
        Route::post('orders/{reference}/items/{item}/downgrade', [BuyerSelfServiceController::class, 'downgrade']);
        Route::post('orders/{reference}/items/{item}/void', [BuyerSelfServiceController::class, 'void']);
        Route::post('orders/{reference}/items/{item}/transfer', [BuyerSelfServiceController::class, 'transfer']);
    });
});
