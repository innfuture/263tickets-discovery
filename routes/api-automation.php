<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Automation\AutomationDataController;
use App\Http\Controllers\Api\Automation\AutomationMessageController;
use App\Http\Controllers\Api\Automation\AutomationOrderController;
use App\Http\Controllers\Api\Automation\AutomationRefundController;
use App\Http\Middleware\EnsureAutomationIdempotency;
use App\Http\Middleware\EnsureAutomationToken;
use App\Models\AutomationToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Automation API (n8n / Zapier / Make)
|--------------------------------------------------------------------------
|
| Loaded from bootstrap/app.php like the scanner + storefront routes.
| All endpoints require a valid `aut_…` bearer token; the scope
| argument to the middleware narrows what each route accepts.
|
| Middleware stack on write endpoints:
|   1. EnsureAutomationToken — auth + scope check (resolves token)
|   2. throttle:automation-token — per-token bucket (uses resolved id)
|   3. EnsureAutomationIdempotency — Idempotency-Key replay cache
|
*/

Route::prefix('api/v1/automations')->group(function () {

    // ── Read endpoints (read scope) ────────────────────────────────
    Route::middleware([
        EnsureAutomationToken::class.':'.AutomationToken::SCOPE_READ,
        'throttle:automation-token',
    ])->group(function () {
        Route::get('events', [AutomationDataController::class, 'events']);
        Route::get('orders', [AutomationDataController::class, 'orders']);
        Route::get('orders/{reference}', [AutomationDataController::class, 'show']);
    });

    // ── Order issuance (orders.write) ──────────────────────────────
    Route::middleware([
        EnsureAutomationToken::class.':'.AutomationToken::SCOPE_ORDERS_WRITE,
        'throttle:automation-token',
        EnsureAutomationIdempotency::class,
    ])->post('orders', [AutomationOrderController::class, 'store']);

    // ── Refund workflow (refunds.write) ────────────────────────────
    Route::middleware([
        EnsureAutomationToken::class.':'.AutomationToken::SCOPE_REFUNDS_WRITE,
        'throttle:automation-token',
        EnsureAutomationIdempotency::class,
    ])->group(function () {
        Route::post('orders/{reference}/refund-requests', [AutomationRefundController::class, 'store']);
        Route::patch('refund-requests/{uuid}', [AutomationRefundController::class, 'update']);
    });

    // ── Broadcast (messages.write) ─────────────────────────────────
    Route::middleware([
        EnsureAutomationToken::class.':'.AutomationToken::SCOPE_MESSAGES_WRITE,
        'throttle:automation-token',
        EnsureAutomationIdempotency::class,
    ])->post('events/{slug}/broadcast', [AutomationMessageController::class, 'broadcast']);
});
