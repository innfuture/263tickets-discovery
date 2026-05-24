<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Public\AddonController;
use App\Http\Controllers\Api\Public\BundleController;
use App\Http\Controllers\Api\Public\CalendarController;
use App\Http\Controllers\Api\Public\CaptchaConfigController;
use App\Http\Controllers\Api\Public\CheckoutController;
use App\Http\Controllers\Api\Public\EventCatalogController;
use App\Http\Controllers\Api\Public\GiftCardController;
use App\Http\Controllers\Api\Public\OrderLookupController;
use App\Http\Controllers\Api\Public\OrganizationProfileController;
use App\Http\Controllers\Api\Public\PrivacyController;
use App\Http\Controllers\Api\Public\QuoteController;
use App\Http\Controllers\Api\Public\RecommendationController;
use App\Http\Controllers\Api\Public\ReferralController;
use App\Http\Controllers\Api\Public\RefundRequestController;
use App\Http\Controllers\Api\Public\SeatController;
use App\Http\Controllers\Api\Public\SitemapController;
use App\Http\Controllers\Api\Public\SitemapIndexController;
use App\Http\Controllers\Api\Public\TransferController;
use App\Http\Controllers\Api\Public\WaitlistController;
use App\Http\Controllers\Api\Public\WalletPassController;
use App\Http\Controllers\Api\Public\WidgetController;
use App\Http\Middleware\EnsureCaptchaPassed;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public storefront API
|--------------------------------------------------------------------------
|
| Stateless JSON surface. No auth — rate limited per-IP. Mutating
| endpoints (create session, waitlist, refund-request, quote) also
| run through the captcha middleware which is a no-op until a real
| provider (Turnstile / hCaptcha) is configured.
|
*/

Route::prefix('api/v1/public')->group(function () {

    // ── Captcha config (cheap, cached by FE) ───────────────────────────
    Route::middleware('throttle:storefront-discovery')->group(function () {
        Route::get('captcha/config', [CaptchaConfigController::class, 'show'])
            ->name('public.captcha.config');
    });

    // ── Discovery ──────────────────────────────────────────────────────
    Route::middleware('throttle:storefront-discovery')->group(function () {
        Route::get('events', [EventCatalogController::class, 'index'])
            ->name('public.events.index');
        Route::get('events/featured', [EventCatalogController::class, 'featured'])
            ->name('public.events.featured');
        Route::get('events/{slug}', [EventCatalogController::class, 'show'])
            ->name('public.events.show');

        Route::get('events/{slug}/seats', [SeatController::class, 'index'])
            ->name('public.events.seats');

        Route::get('events/{slug}/addons', [AddonController::class, 'index'])
            ->name('public.events.addons');

        Route::get('events/{slug}/recommendations', [RecommendationController::class, 'index'])
            ->name('public.events.recommendations');

        Route::get('events/{slug}/predictions', [\App\Http\Controllers\Api\Public\EventPredictionController::class, 'show'])
            ->name('public.events.predictions');

        Route::get('gift-cards/{code}/balance', [GiftCardController::class, 'balance'])
            ->name('public.giftcards.balance');

        Route::get('referral/{code}', [ReferralController::class, 'show'])
            ->name('public.referral.show');

        Route::get('bundles/{bundleSlug}', [BundleController::class, 'show'])
            ->name('public.bundles.show');

        Route::get('organizers/{slug}/bundles', [BundleController::class, 'listForOrganizer'])
            ->name('public.organizers.bundles');

        Route::get('organizers/{slug}', [OrganizationProfileController::class, 'show'])
            ->name('public.organizers.show');
        Route::get('organizers/{slug}/events', [OrganizationProfileController::class, 'events'])
            ->name('public.organizers.events');
    });

    // ── Checkout ───────────────────────────────────────────────────────
    Route::middleware('throttle:storefront-checkout')->group(function () {
        // Captcha gates only the create — the rest of the session
        // flow has the uuid as a capability token.
        Route::middleware(EnsureCaptchaPassed::class)->group(function () {
            Route::post('checkout/sessions', [CheckoutController::class, 'create'])
                ->name('public.checkout.create');
        });

        Route::get('checkout/sessions/{uuid}', [CheckoutController::class, 'show'])
            ->name('public.checkout.show');
        Route::patch('checkout/sessions/{uuid}/items', [CheckoutController::class, 'updateItems'])
            ->name('public.checkout.items');
        Route::patch('checkout/sessions/{uuid}/seats', [SeatController::class, 'update'])
            ->name('public.checkout.seats');
        Route::patch('checkout/sessions/{uuid}/attendees', [CheckoutController::class, 'updateAttendees'])
            ->name('public.checkout.attendees');
        Route::patch('checkout/sessions/{uuid}/addons', [AddonController::class, 'update'])
            ->name('public.checkout.addons');
        Route::post('checkout/sessions/{uuid}/promo', [CheckoutController::class, 'applyPromo'])
            ->name('public.checkout.promo.apply');
        Route::delete('checkout/sessions/{uuid}/promo', [CheckoutController::class, 'clearPromo'])
            ->name('public.checkout.promo.clear');
        Route::post('checkout/sessions/{uuid}/gift-card', [GiftCardController::class, 'apply'])
            ->name('public.checkout.giftcard.apply');
        Route::delete('checkout/sessions/{uuid}/gift-card', [GiftCardController::class, 'clear'])
            ->name('public.checkout.giftcard.clear');
        Route::post('checkout/sessions/{uuid}/pay', [CheckoutController::class, 'pay'])
            ->name('public.checkout.pay');
        Route::post('checkout/sessions/{uuid}/confirm', [CheckoutController::class, 'confirm'])
            ->name('public.checkout.confirm');
        Route::delete('checkout/sessions/{uuid}', [CheckoutController::class, 'abandon'])
            ->name('public.checkout.abandon');
    });

    // ── Orders ─────────────────────────────────────────────────────────
    Route::middleware('throttle:storefront-lookup')->group(function () {
        Route::post('orders/lookup', [OrderLookupController::class, 'lookup'])
            ->name('public.orders.lookup');
        Route::get('orders/{reference}', [OrderLookupController::class, 'show'])
            ->name('public.orders.show'); // signed URL
        Route::get('orders/{reference}/items/{item}/pass', [WalletPassController::class, 'show'])
            ->name('public.orders.pass'); // signed URL

        Route::get('orders/{reference}/calendar.ics', [CalendarController::class, 'show'])
            ->name('public.orders.calendar'); // signed URL

        // Buyer-to-buyer ticket transfers / resale.
        Route::post('orders/{reference}/items/{item}/transfer', [TransferController::class, 'offer'])
            ->name('public.orders.transfer.offer'); // signed URL
        Route::post('orders/{reference}/items/{item}/transfer/{uuid}/revoke', [TransferController::class, 'revoke'])
            ->name('public.orders.transfer.revoke'); // signed URL

        Route::get('transfers/claim/{token}', [TransferController::class, 'show'])
            ->name('public.transfers.show');
        Route::post('transfers/claim/{token}/accept', [TransferController::class, 'accept'])
            ->name('public.transfers.accept');
        Route::post('transfers/claim/{token}/decline', [TransferController::class, 'decline'])
            ->name('public.transfers.decline');

        Route::middleware(EnsureCaptchaPassed::class)
            ->post('orders/{reference}/refund-request', [RefundRequestController::class, 'store'])
            ->name('public.orders.refund_request');
    });

    // ── Waitlist + group quotes ────────────────────────────────────────
    Route::middleware('throttle:storefront-waitlist')->group(function () {
        Route::middleware(EnsureCaptchaPassed::class)
            ->post('events/{slug}/waitlist', [WaitlistController::class, 'store'])
            ->name('public.waitlist.store');
        Route::middleware(EnsureCaptchaPassed::class)
            ->post('events/{slug}/quote', [QuoteController::class, 'store'])
            ->name('public.quote.store');
    });

    // ── Privacy (GDPR / CCPA) ──────────────────────────────────────────
    Route::middleware(['throttle:storefront-lookup', EnsureCaptchaPassed::class])->group(function () {
        Route::post('privacy/export', [PrivacyController::class, 'export'])
            ->name('public.privacy.export');
        Route::post('privacy/erase', [PrivacyController::class, 'erase'])
            ->name('public.privacy.erase');
    });
});

// ── Sitemaps (top-level) ───────────────────────────────────────────────
Route::middleware('throttle:storefront-discovery')->group(function () {
    Route::get('sitemap.xml', [SitemapController::class, 'index'])
        ->name('public.sitemap');
    Route::get('sitemap-index.xml', [SitemapIndexController::class, 'index'])
        ->name('public.sitemap.index');
    Route::get('sitemap-events-{shard}.xml', [SitemapIndexController::class, 'events'])
        ->whereNumber('shard')->name('public.sitemap.events');
    Route::get('sitemap-organizers-{shard}.xml', [SitemapIndexController::class, 'organizers'])
        ->whereNumber('shard')->name('public.sitemap.organizers');
});

// ── Embeddable widget (top-level, CORS-open) ───────────────────────────
Route::middleware('throttle:storefront-discovery')->group(function () {
    Route::get('widget/v1/embed.js', [WidgetController::class, 'script'])
        ->name('public.widget.script');
    Route::get('widget/v1/event/{slug}.json', [WidgetController::class, 'event'])
        ->name('public.widget.event');
});
