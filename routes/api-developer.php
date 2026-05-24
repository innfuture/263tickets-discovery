<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Developer\DeveloperPortalController;
use App\Http\Controllers\Api\Developer\DeveloperPublicApiController;
use App\Http\Middleware\EnsureDeveloperApiKey;
use App\Http\Middleware\EnsureDeveloperPortalToken;
use App\Http\Middleware\TrackDeveloperApiUsage;
use App\Models\DeveloperApiKey;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Developer API (tiered)
|--------------------------------------------------------------------------
|
|   /api/developer/portal/*  Account + key + subscription self-service.
|   /api/developer/v1/*      The read-only public API consumers call.
|
| Tier enforcement happens inside EnsureDeveloperApiKey (scope check
| + monthly quota), then `throttle:developer-key` for per-key bucket.
|
*/

// ── Portal ─────────────────────────────────────────────────────────────
// Registration is open. Everything else requires the per-account
// bootstrap token issued at registration as Authorization: Bearer.
Route::prefix('api/developer/portal')
    ->middleware('throttle:storefront-checkout')
    ->group(function () {
        Route::post('accounts', [DeveloperPortalController::class, 'register'])
            ->name('developer.portal.register');

        Route::middleware(EnsureDeveloperPortalToken::class)->group(function () {
            Route::get('accounts/{uuid}', [DeveloperPortalController::class, 'show'])
                ->name('developer.portal.show');
            Route::post('accounts/{uuid}/keys', [DeveloperPortalController::class, 'issueKey'])
                ->name('developer.portal.issue_key');
            Route::get('accounts/{uuid}/keys', [DeveloperPortalController::class, 'listKeys'])
                ->name('developer.portal.list_keys');
            Route::delete('accounts/{uuid}/keys/{keyUuid}', [DeveloperPortalController::class, 'revokeKey'])
                ->name('developer.portal.revoke_key');
            Route::post('accounts/{uuid}/subscribe', [DeveloperPortalController::class, 'subscribe'])
                ->name('developer.portal.subscribe');
            Route::post('accounts/{uuid}/rotate-token', [DeveloperPortalController::class, 'rotateToken'])
                ->name('developer.portal.rotate_token');
        });
    });

// ── Public Developer API v1 ────────────────────────────────────────────
Route::prefix('api/developer/v1')
    ->middleware(['throttle:developer-key', TrackDeveloperApiUsage::class])
    ->group(function () {
        // Free+
        Route::middleware(EnsureDeveloperApiKey::class.':'.DeveloperApiKey::SCOPE_EVENTS_LIST)
            ->get('events', [DeveloperPublicApiController::class, 'events'])
            ->name('developer.v1.events');

        Route::middleware(EnsureDeveloperApiKey::class.':'.DeveloperApiKey::SCOPE_EVENTS_READ)
            ->get('events/{slug}', [DeveloperPublicApiController::class, 'event'])
            ->name('developer.v1.events.show');

        // Basic+
        Route::middleware(EnsureDeveloperApiKey::class.':'.DeveloperApiKey::SCOPE_ORDERS_AGGREGATE)
            ->get('orders/aggregate', [DeveloperPublicApiController::class, 'ordersAggregate'])
            ->name('developer.v1.orders.aggregate');

        // Enterprise+
        Route::middleware(EnsureDeveloperApiKey::class.':'.DeveloperApiKey::SCOPE_ANALYTICS_READ)
            ->group(function () {
                Route::get('analytics/events', [DeveloperPublicApiController::class, 'analyticsEvents'])
                    ->name('developer.v1.analytics.events');
                Route::get('analytics/cross-org', \App\Http\Controllers\Api\Developer\CrossOrgAnalyticsController::class)
                    ->name('developer.v1.analytics.cross_org');
            });
    });
