<?php

use App\Http\Controllers\AdCampaignController;
use App\Http\Controllers\EventAnalyticsController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\TicketCategoryController;
use App\Http\Middleware\EnsureTeamMembership;
use App\Http\Middleware\TrackEventPageView;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', ValidateSessionWithWorkOS::class, EnsureTeamMembership::class])
    ->group(function () {
        Route::inertia('dashboard', 'dashboard')->name('dashboard');

        Route::get('events', [EventController::class, 'index'])->name('events.index');
        Route::post('events', [EventController::class, 'store'])->name('events.store');
        Route::get('events/{event:slug}/edit', [EventController::class, 'edit'])->name('events.edit');
        Route::patch('events/{event:slug}', [EventController::class, 'update'])->name('events.update');
        Route::patch('events/{event:slug}/seo', [EventController::class, 'updateSeo'])->name('events.seo.update');
        Route::post('events/{event:slug}/media', [EventController::class, 'storeMedia'])->name('events.media.store');
        Route::delete('events/{event:slug}/media/{media}', [EventController::class, 'destroyMedia'])->name('events.media.destroy');
        Route::post('events/{event:slug}/lineup/photo', [EventController::class, 'storeLineupPhoto'])->name('events.lineup.photo');
        Route::post('events/{event:slug}/sponsors/logo', [EventController::class, 'storeSponsorLogo'])->name('events.sponsors.logo');
        Route::get('events/{event:slug}', [EventController::class, 'show'])
            ->name('events.show')
            ->middleware(TrackEventPageView::class);

        // ── Ticket categories ──────────────────────────────────────────────────
        Route::get('events/{event:slug}/tickets', [TicketCategoryController::class, 'index'])->name('tickets.index');
        Route::post('events/{event:slug}/tickets', [TicketCategoryController::class, 'store'])->name('tickets.store');
        Route::patch('events/{event:slug}/tickets/{category}', [TicketCategoryController::class, 'update'])->name('tickets.update');
        Route::delete('events/{event:slug}/tickets/{category}', [TicketCategoryController::class, 'destroy'])->name('tickets.destroy');
        Route::get('events/{event:slug}/tickets/{category}/status', [TicketCategoryController::class, 'generationStatus'])->name('tickets.generation-status');
        Route::patch('events/{event:slug}/tickets/{category}/sale-status', [TicketCategoryController::class, 'updateSaleStatus'])->name('tickets.sale-status');
        Route::post('events/{event:slug}/tickets/{category}/discounts', [TicketCategoryController::class, 'storeDiscount'])->name('tickets.discounts.store');
        Route::post('events/{event:slug}/tickets/{category}/promo-codes', [TicketCategoryController::class, 'storePromoCode'])->name('tickets.promo-codes.store');

        // ── Analytics ─────────────────────────────────────────────────────────
        Route::get('events/{event:slug}/analytics', [EventAnalyticsController::class, 'index'])->name('events.analytics');
        Route::post('events/{event:slug}/analytics/track', [EventAnalyticsController::class, 'track'])->name('events.analytics.track');

        // ── Ad campaigns ──────────────────────────────────────────────────────
        Route::get('events/{event:slug}/ads', [AdCampaignController::class, 'index'])->name('ads.index');
        Route::post('events/{event:slug}/ads', [AdCampaignController::class, 'store'])->name('ads.store');
        Route::post('events/{event:slug}/ads/{campaign}/pause', [AdCampaignController::class, 'pause'])->name('ads.pause');
        Route::post('events/{event:slug}/ads/{campaign}/sync', [AdCampaignController::class, 'syncMetrics'])->name('ads.sync');
        Route::delete('events/{event:slug}/ads/{campaign}', [AdCampaignController::class, 'destroy'])->name('ads.destroy');
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
});

Route::middleware(['auth', ValidateSessionWithWorkOS::class, 'throttle:30,1'])
    ->get('geocode/search', [EventController::class, 'geocodeSearch'])
    ->name('geocode.search');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
