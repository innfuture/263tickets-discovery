<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Public\StakeholderMarketplaceController;
use App\Http\Controllers\Api\Stakeholder\StakeholderAuthController;
use App\Http\Controllers\Api\Stakeholder\StakeholderDashboardController;
use App\Http\Controllers\Api\Stakeholder\StakeholderEngagementController;
use App\Http\Middleware\EnsureStakeholderAuth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Stakeholder Portal API
|--------------------------------------------------------------------------
|
| Magic-link auth, session bearer for the authed surface. Public
| marketplace under /api/v1/public/stakeholders for browsing.
|
*/

// ── Public stakeholder marketplace ─────────────────────────────────────
Route::prefix('api/v1/public/stakeholders')
    ->middleware('throttle:storefront-discovery')
    ->group(function () {
        Route::get('/', [StakeholderMarketplaceController::class, 'index'])
            ->name('public.stakeholders.index');
        Route::get('{uuid}', [StakeholderMarketplaceController::class, 'show'])
            ->name('public.stakeholders.show');
    });

// ── Stakeholder auth + dashboard ───────────────────────────────────────
Route::prefix('api/v1/stakeholder')->group(function () {

    Route::middleware('throttle:storefront-lookup')->group(function () {
        Route::post('auth/register', [StakeholderAuthController::class, 'register']);
        Route::post('auth/request-link', [StakeholderAuthController::class, 'requestLink']);
        Route::post('auth/verify', [StakeholderAuthController::class, 'verify']);
    });

    Route::middleware(['throttle:storefront-discovery', EnsureStakeholderAuth::class])->group(function () {
        Route::post('auth/logout', [StakeholderAuthController::class, 'logout']);

        Route::get('me', [StakeholderDashboardController::class, 'me']);
        Route::patch('me/profile', [StakeholderDashboardController::class, 'updateProfile']);

        Route::get('invitations', [StakeholderDashboardController::class, 'invitations']);
        Route::get('applications', [StakeholderDashboardController::class, 'applications']);
        Route::get('engagements', [StakeholderDashboardController::class, 'engagements']);
        Route::get('engagements/{uuid}', [StakeholderDashboardController::class, 'engagement']);

        Route::get('services', [StakeholderDashboardController::class, 'services']);
        Route::post('services', [StakeholderDashboardController::class, 'storeService']);
        Route::patch('services/{uuid}', [StakeholderDashboardController::class, 'updateService']);
        Route::delete('services/{uuid}', [StakeholderDashboardController::class, 'destroyService']);

        // Lifecycle actions
        Route::post('invitations/{uuid}/accept', [StakeholderEngagementController::class, 'acceptInvitation']);
        Route::post('invitations/{uuid}/decline', [StakeholderEngagementController::class, 'declineInvitation']);
        Route::post('applications', [StakeholderEngagementController::class, 'apply']);
        Route::post('applications/{uuid}/withdraw', [StakeholderEngagementController::class, 'withdrawApplication']);
        Route::post('engagements/{engagement}/deliverables/{deliverable}/submit', [StakeholderEngagementController::class, 'submitDeliverable']);

        // Disputes (stakeholder side)
        Route::get('disputes', [\App\Http\Controllers\Api\Stakeholder\StakeholderDisputeController::class, 'index']);
        Route::post('engagements/{engagementUuid}/disputes', [\App\Http\Controllers\Api\Stakeholder\StakeholderDisputeController::class, 'open']);
        Route::post('disputes/{uuid}/evidence', [\App\Http\Controllers\Api\Stakeholder\StakeholderDisputeController::class, 'appendEvidence']);
        Route::post('disputes/{uuid}/withdraw', [\App\Http\Controllers\Api\Stakeholder\StakeholderDisputeController::class, 'withdraw']);
    });
});
