<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Extensions\DeveloperPortalController;
use App\Http\Controllers\Api\Extensions\ExtensionRuntimeController;
use App\Http\Controllers\Api\Extensions\MarketplaceController;
use App\Http\Middleware\EnsureExtensionInstallation;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Extension marketplace + extension runtime API
|--------------------------------------------------------------------------
|
| Three surfaces:
|   /api/v1/marketplace/*   — public browse (no auth)
|   /api/v1/extensions/dev/* — developer-portal (sig-auth via submit)
|   /api/v1/extensions/*    — runtime API for installed extensions
|                              (Bearer ext_… token)
|
*/

// ── Marketplace browse (public) ────────────────────────────────────────
Route::prefix('api/v1/marketplace')
    ->middleware('throttle:storefront-discovery')
    ->group(function () {
        Route::get('extensions', [MarketplaceController::class, 'index'])
            ->name('marketplace.extensions.index');
        Route::get('extensions/{slug}', [MarketplaceController::class, 'show'])
            ->name('marketplace.extensions.show');
        Route::get('permissions', [MarketplaceController::class, 'permissions'])
            ->name('marketplace.permissions');
    });

// ── Developer portal (open registration; submissions sig-verified) ─────
Route::prefix('api/v1/extensions/dev')
    ->middleware('throttle:storefront-checkout')
    ->group(function () {
        Route::post('register', [DeveloperPortalController::class, 'register'])
            ->name('extensions.dev.register');
        Route::post('developers/{uuid}/submissions', [DeveloperPortalController::class, 'submit'])
            ->name('extensions.dev.submit');
        Route::get('developers/{uuid}/versions', [DeveloperPortalController::class, 'versions'])
            ->name('extensions.dev.versions');
    });

// ── Extension runtime (Bearer ext_…) ───────────────────────────────────
Route::prefix('api/v1/extensions')->group(function () {
    Route::middleware([EnsureExtensionInstallation::class])
        ->get('me', [ExtensionRuntimeController::class, 'me']);

    Route::middleware([
        EnsureExtensionInstallation::class.':'.\App\Services\Extensions\ExtensionPermission::EVENTS_READ,
        'throttle:automation-token',
    ])->get('events', [ExtensionRuntimeController::class, 'events']);

    Route::middleware([
        EnsureExtensionInstallation::class.':'.\App\Services\Extensions\ExtensionPermission::ORDERS_READ,
        'throttle:automation-token',
    ])->group(function () {
        Route::get('orders', [ExtensionRuntimeController::class, 'orders']);
        Route::get('orders/{reference}', [ExtensionRuntimeController::class, 'order']);
    });
});
