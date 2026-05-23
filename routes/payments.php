<?php

use App\Http\Controllers\Payments\CheckoutController;
use App\Http\Controllers\Payments\Sandbox\BnplController;
use App\Http\Controllers\Payments\Sandbox\ChargeUiController;
use App\Http\Controllers\Payments\Sandbox\ClockController;
use App\Http\Controllers\Payments\Sandbox\DashboardController;
use App\Http\Controllers\Payments\Sandbox\DisputeController;
use App\Http\Controllers\Payments\Sandbox\InspectorController;
use App\Http\Controllers\Payments\Sandbox\MerchantController;
use App\Http\Controllers\Payments\Sandbox\Service\ChargeServiceController;
use App\Http\Controllers\Payments\Sandbox\Service\RefundServiceController;
use App\Http\Controllers\Payments\Sandbox\Service\StatusServiceController;
use App\Http\Controllers\Payments\Sandbox\ThreeDsController;
use App\Http\Controllers\Payments\Sandbox\TransactionController;
use App\Http\Controllers\Payments\Sandbox\WebhookAdminController;
use App\Http\Controllers\Payments\StatusController;
use App\Http\Controllers\Payments\WebhookController;
use App\Http\Middleware\SandboxServiceAuth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payment routes
|--------------------------------------------------------------------------
|
| Loaded from bootstrap/app.php alongside the web routes. Two surfaces:
|
|   /payments/charge                   POST  authenticated charge initiation
|   /payments/{transaction}/status     GET   SPA polling
|   /payments/webhooks/{gateway}       POST  inbound from providers (CSRF-exempt)
|
| The webhook path is excluded from CSRF in the bootstrap closure, so
| it accepts unsigned POSTs from external networks. Per-driver signature
| verification happens inside WebhookController::handle().
|
*/

Route::middleware('web')->group(function (): void {
    Route::post('/payments/charge', [CheckoutController::class, 'charge'])
        ->middleware('auth')
        ->name('payments.charge');

    Route::get('/payments/{transaction:uuid}/status', [StatusController::class, 'show'])
        ->name('payments.status');
});

Route::post('/payments/webhooks/{gateway}', [WebhookController::class, 'handle'])
    ->name('payments.webhook');

/*
 |--------------------------------------------------------------------------
 | Sandbox admin surface (§9 production guard)
 |--------------------------------------------------------------------------
 | Mounted only when SandboxKernel is enabled. CSRF-exempt (admin tools
 | + tests POST without sessions); inside-the-trust-boundary auth lives
 | in the per-route middleware once a sandbox.* permission is added.
 */
if ((bool) config('payments.gateways.sandbox.enabled', false)) {
    Route::prefix('sandbox/payments')->name('payments.sandbox.')->group(function (): void {
        // UI (Inertia)
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('charge', [ChargeUiController::class, 'store'])->name('charge.store');
        Route::get('transactions/{transaction:uuid}/inspect', [InspectorController::class, 'show'])->name('inspector.show');
        Route::get('transactions/{transaction:uuid}/diff', [InspectorController::class, 'diff'])->name('inspector.diff');

        // 3DS mock ACS (§5.1, phase 5)
        Route::get('three-ds/acs', [ThreeDsController::class, 'show'])->name('three-ds.show');
        Route::post('three-ds/acs', [ThreeDsController::class, 'submit'])->name('three-ds.submit');

        // BNPL hosted checkout (Klarna / Afterpay / Affirm mocks)
        Route::get('bnpl/{provider}', [BnplController::class, 'show'])->name('bnpl.show');
        Route::post('bnpl/{provider}', [BnplController::class, 'decide'])->name('bnpl.decide');

        // Clock
        Route::get('clock/{merchant:slug}', [ClockController::class, 'show'])->name('clock.show');
        Route::post('clock/{merchant:slug}/advance', [ClockController::class, 'advance'])->name('clock.advance');
        Route::post('clock/{merchant:slug}/reset', [ClockController::class, 'reset'])->name('clock.reset');

        // Webhook admin
        Route::get('webhooks', [WebhookAdminController::class, 'index'])->name('webhooks.index');
        Route::post('webhooks/{event:uuid}/replay', [WebhookAdminController::class, 'replay'])->name('webhooks.replay');
        Route::post('webhooks/{event:uuid}/drop', [WebhookAdminController::class, 'drop'])->name('webhooks.drop');
        Route::post('webhooks/{merchant:slug}/inject', [WebhookAdminController::class, 'inject'])->name('webhooks.inject');
        Route::post('webhooks/{merchant:slug}/flush', [WebhookAdminController::class, 'flush'])->name('webhooks.flush');

        // Transactions
        Route::get('transactions/{transaction:uuid}', [TransactionController::class, 'show'])->name('transaction.show');
        Route::post('transactions/{transaction:uuid}/capture', [TransactionController::class, 'capture'])->name('transaction.capture');
        Route::post('transactions/{transaction:uuid}/void', [TransactionController::class, 'void'])->name('transaction.void');

        // Merchants
        Route::get('merchants/{merchant:slug}', [MerchantController::class, 'show'])->name('merchant.show');
        Route::patch('merchants/{merchant:slug}', [MerchantController::class, 'update'])->name('merchant.update');
        Route::post('merchants/{merchant:slug}/rotate-webhook-secret', [MerchantController::class, 'rotateWebhookSecret'])->name('merchant.rotate-secret');

        // Disputes
        Route::post('disputes/{transaction:uuid}/open', [DisputeController::class, 'open'])->name('dispute.open');
        Route::post('disputes/{dispute:uuid}/resolve', [DisputeController::class, 'resolve'])->name('dispute.resolve');
    });
}

/*
 |--------------------------------------------------------------------------
 | Sandbox HTTP-served surface (§7, phase 6)
 |--------------------------------------------------------------------------
 | Exposes the kernel over HTTP so a remote SandboxGateway (kernel=http)
 | can drive it. Mounted whenever a `service_key` is configured — this
 | lets the same app act as both consumer (in-process) and server (HTTP)
 | during dev. Auth is Bearer-only; no sessions, no CSRF.
 */
if ((string) config('payments.sandbox.service_key', '') !== ''
    && (bool) config('payments.gateways.sandbox.enabled', false)) {
    Route::prefix('sandbox/service/v1')
        ->middleware(SandboxServiceAuth::class)
        ->name('payments.sandbox.service.')
        ->group(function (): void {
            Route::post('charges', [ChargeServiceController::class, 'store'])->name('charges.store');
            Route::post('refunds', [RefundServiceController::class, 'store'])->name('refunds.store');
            Route::get('transactions', [StatusServiceController::class, 'show'])->name('transactions.show');
        });
}
