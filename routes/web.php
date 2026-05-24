<?php

use App\Http\Controllers\AdCampaignController;
use App\Http\Controllers\AttendeeController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscountsController;
use App\Http\Controllers\EventAnalyticsController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\Organizations\OrganizationInvitationController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ScannersController;
use App\Http\Controllers\TicketCategoryController;
use App\Http\Middleware\EnsureOrganizationMembership;
use App\Http\Middleware\TrackEventPageView;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

Route::inertia('/', 'welcome')->name('home');

// Everything inside the org scope: dashboard, events, tickets, ads,
// analytics. The `{current_organization}` slug is the active org's
// public identifier; EnsureOrganizationMembership both authorises
// access and swaps the user's active context if they navigated to a
// different org they belong to.
Route::prefix('{current_organization}')
    ->middleware(['auth', ValidateSessionWithWorkOS::class, EnsureOrganizationMembership::class])
    ->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // ── Calendar (Plan) ────────────────────────────────────────────────────
        Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');

        // ── Attendees (Engage) ─────────────────────────────────────────────────
        Route::get('attendees', [AttendeeController::class, 'index'])->name('attendees.index');
        Route::get('attendees/export', [AttendeeController::class, 'export'])->name('attendees.export');

        // ── Check-in (Operate) ─────────────────────────────────────────────────
        Route::get('check-in', [CheckInController::class, 'index'])->name('check-in.index');
        Route::post('check-in/scan', [CheckInController::class, 'scan'])->name('check-in.scan');

        // ── Reports + Finance (Sell) ───────────────────────────────────────────
        Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');
        Route::get('finance', [FinanceController::class, 'index'])->name('finance.index');
        Route::get('finance/refunds/{refund:uuid}', [FinanceController::class, 'showRefund'])->name('finance.refund.show');
        Route::post('finance/refunds/{refund:uuid}/process', [FinanceController::class, 'processRefund'])->name('finance.refund.process');
        Route::post('finance/refunds/{refund:uuid}/deny', [FinanceController::class, 'denyRefund'])->name('finance.refund.deny');

        // ── Discounts library (Sell) ───────────────────────────────────────────
        Route::get('discounts', [DiscountsController::class, 'index'])->name('discounts.index');
        Route::post('discounts', [DiscountsController::class, 'storeDiscount'])->name('discounts.store');
        Route::post('discounts/{discount}/toggle', [DiscountsController::class, 'toggleDiscount'])->name('discounts.toggle');
        Route::post('promo-codes', [DiscountsController::class, 'storePromo'])->name('discounts.promo.store');
        Route::post('promo-codes/{promo}/toggle', [DiscountsController::class, 'togglePromo'])->name('discounts.promo.toggle');

        // ── Notifications inbox (Operate) ──────────────────────────────────────
        Route::get('notifications', [NotificationsController::class, 'index'])->name('notifications.index');

        // ── Scanners (Operate) — mobile/kiosk pairing + monitoring ────────────
        Route::get('scanners', [ScannersController::class, 'index'])->name('scanners.index');
        Route::post('scanners', [ScannersController::class, 'store'])->name('scanners.store');
        Route::get('scanners/{profile:uuid}', [ScannersController::class, 'show'])->name('scanners.show');
        Route::patch('scanners/{profile:uuid}', [ScannersController::class, 'update'])->name('scanners.update');
        Route::delete('scanners/{profile:uuid}', [ScannersController::class, 'destroy'])->name('scanners.destroy');
        Route::post('scanners/{profile:uuid}/pairing-codes', [ScannersController::class, 'issuePairingCode'])->name('scanners.pairing.issue');
        Route::post('scanners/{profile:uuid}/devices/{device:uuid}/revoke', [ScannersController::class, 'revokeDevice'])
            ->withoutScopedBindings()->name('scanners.devices.revoke');

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
        Route::patch('events/{event:slug}/tickets/{category:uuid}', [TicketCategoryController::class, 'update'])
            ->withoutScopedBindings()->name('tickets.update');
        Route::delete('events/{event:slug}/tickets/{category:uuid}', [TicketCategoryController::class, 'destroy'])
            ->withoutScopedBindings()->name('tickets.destroy');
        Route::get('events/{event:slug}/tickets/{category:uuid}/status', [TicketCategoryController::class, 'generationStatus'])
            ->withoutScopedBindings()->name('tickets.generation-status');
        Route::patch('events/{event:slug}/tickets/{category:uuid}/sale-status', [TicketCategoryController::class, 'updateSaleStatus'])
            ->withoutScopedBindings()->name('tickets.sale-status');
        Route::post('events/{event:slug}/tickets/{category:uuid}/adjust', [TicketCategoryController::class, 'adjust'])
            ->withoutScopedBindings()->name('tickets.adjust');
        Route::post('events/{event:slug}/tickets/{category:uuid}/discounts', [TicketCategoryController::class, 'storeDiscount'])
            ->withoutScopedBindings()->name('tickets.discounts.store');
        Route::post('events/{event:slug}/tickets/{category:uuid}/promo-codes', [TicketCategoryController::class, 'storePromoCode'])
            ->withoutScopedBindings()->name('tickets.promo-codes.store');

        // ── Analytics ─────────────────────────────────────────────────────────
        Route::get('events/{event:slug}/analytics', [EventAnalyticsController::class, 'index'])->name('events.analytics');
        Route::post('events/{event:slug}/analytics/track', [EventAnalyticsController::class, 'track'])->name('events.analytics.track');

        // ── Ad campaigns ──────────────────────────────────────────────────────
        Route::get('events/{event:slug}/ads', [AdCampaignController::class, 'index'])->name('ads.index');
        Route::post('events/{event:slug}/ads', [AdCampaignController::class, 'store'])->name('ads.store');
        Route::post('events/{event:slug}/ads/{campaign}/pause', [AdCampaignController::class, 'pause'])->name('ads.pause');
        Route::post('events/{event:slug}/ads/{campaign}/sync', [AdCampaignController::class, 'syncMetrics'])->name('ads.sync');
        Route::delete('events/{event:slug}/ads/{campaign}', [AdCampaignController::class, 'destroy'])->name('ads.destroy');

        // ── Orders (cross-event order management) ─────────────────────────────
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/resend', [OrderController::class, 'resend'])->name('orders.resend');
        Route::post('orders/{order}/refund', [OrderController::class, 'refund'])->name('orders.refund');

        // ── Marketing (campaigns, social, paid ads, integrations) ─────────────
        Route::get('marketing', [MarketingController::class, 'index'])->name('marketing.index');
        Route::get('marketing/email-campaigns', [MarketingController::class, 'emailCampaigns'])->name('marketing.email-campaigns');
        Route::get('marketing/social', [MarketingController::class, 'social'])->name('marketing.social');
        Route::get('marketing/paid-ads', [MarketingController::class, 'paidAds'])->name('marketing.paid-ads');
        Route::get('marketing/integrations', [MarketingController::class, 'integrations'])->name('marketing.integrations');

        // Marketing mutations — drafts, sends, social posts, integration connect/disconnect.
        Route::post('marketing/email-campaigns', [MarketingController::class, 'storeEmailCampaign'])->name('marketing.email-campaigns.store');
        Route::post('marketing/email-campaigns/{campaign}/send', [MarketingController::class, 'sendEmailCampaign'])->name('marketing.email-campaigns.send');
        Route::post('marketing/social', [MarketingController::class, 'storeSocialPost'])->name('marketing.social.store');
        Route::post('marketing/integrations/{provider}/connect', [MarketingController::class, 'connectIntegration'])->name('marketing.integrations.connect');
        Route::post('marketing/integrations/{provider}/disconnect', [MarketingController::class, 'disconnectIntegration'])->name('marketing.integrations.disconnect');
    });

// ── Public organizer profile — Eventbrite-style /o/{slug} ──────────────────
Route::get('o/{organization:slug}', [OrganizationController::class, 'show'])
    ->name('organizations.show');

// Org invitation accept (auth, no org scope — the link arrives by email
// before the recipient has any org context).
Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [OrganizationInvitationController::class, 'accept'])
        ->name('invitations.accept');
});

Route::middleware(['auth', ValidateSessionWithWorkOS::class, 'throttle:30,1'])
    ->get('geocode/search', [EventController::class, 'geocodeSearch'])
    ->name('geocode.search');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
