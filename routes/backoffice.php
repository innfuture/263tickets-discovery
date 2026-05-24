<?php

declare(strict_types=1);

use App\Http\Controllers\BackOffice\Automation\AutomationTokenController;
use App\Http\Controllers\BackOffice\Automation\AutomationWebhookController;
use App\Http\Controllers\BackOffice\Automation\WebhookDeliveryController;
use App\Http\Controllers\BackOffice\Storefront\AddonManagementController;
use App\Http\Controllers\BackOffice\Storefront\ApprovalQueueController;
use App\Http\Controllers\BackOffice\Storefront\BundleManagementController;
use App\Http\Controllers\BackOffice\Storefront\EventTemplateController;
use App\Http\Controllers\BackOffice\Storefront\OrganizationDomainController;
use App\Http\Controllers\BackOffice\Storefront\QuoteConversionController;
use App\Http\Controllers\BackOffice\Storefront\RefundPolicyController;
use App\Http\Controllers\BackOffice\Storefront\RefundRequestReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Back-office JSON API
|--------------------------------------------------------------------------
|
| Authenticated organizer-side endpoints. Required from routes/web.php
| inside the existing `{current_organization}` group so they inherit:
|
|   - prefix /{current_organization}/api/back-office/
|   - middleware: auth + WorkOS + EnsureOrganizationMembership
|   - bound: $currentOrganization (Organization model, via Route::bind)
|
| All routes return JSON; the Inertia dashboard pages consume them.
|
*/

Route::prefix('api/back-office')->name('back-office.')->group(function () {

    // Bundles
    Route::get('bundles', [BundleManagementController::class, 'index'])->name('bundles.index');
    Route::post('bundles', [BundleManagementController::class, 'store'])->name('bundles.store');
    Route::patch('bundles/{bundle:slug}', [BundleManagementController::class, 'update'])->name('bundles.update');
    Route::delete('bundles/{bundle:slug}', [BundleManagementController::class, 'destroy'])->name('bundles.destroy');

    // Addons (per-event)
    Route::get('events/{event:slug}/addons', [AddonManagementController::class, 'index'])->name('addons.index');
    Route::post('events/{event:slug}/addons', [AddonManagementController::class, 'store'])->name('addons.store');
    Route::patch('addons/{addon:uuid}', [AddonManagementController::class, 'update'])->name('addons.update');
    Route::delete('addons/{addon:uuid}', [AddonManagementController::class, 'destroy'])->name('addons.destroy');

    // Per-event refund policy
    Route::get('events/{event:slug}/refund-policy', [RefundPolicyController::class, 'show'])->name('refund-policy.show');
    Route::put('events/{event:slug}/refund-policy', [RefundPolicyController::class, 'update'])->name('refund-policy.update');

    // Approval queue (orders in pending_approval)
    Route::get('approvals', [ApprovalQueueController::class, 'index'])->name('approvals.index');
    Route::post('approvals/{order:reference}/approve', [ApprovalQueueController::class, 'approve'])->name('approvals.approve');
    Route::post('approvals/{order:reference}/reject', [ApprovalQueueController::class, 'reject'])->name('approvals.reject');

    // Recurring event templates
    Route::get('event-templates', [EventTemplateController::class, 'index'])->name('event-templates.index');
    Route::post('event-templates', [EventTemplateController::class, 'store'])->name('event-templates.store');
    Route::patch('event-templates/{template:uuid}', [EventTemplateController::class, 'update'])->name('event-templates.update');
    Route::delete('event-templates/{template:uuid}', [EventTemplateController::class, 'destroy'])->name('event-templates.destroy');

    // Refund requests (review queue)
    Route::get('refund-requests', [RefundRequestReviewController::class, 'index'])->name('refund-requests.index');
    Route::post('refund-requests/{refundRequest:uuid}/approve', [RefundRequestReviewController::class, 'approve'])->name('refund-requests.approve');
    Route::post('refund-requests/{refundRequest:uuid}/reject', [RefundRequestReviewController::class, 'reject'])->name('refund-requests.reject');

    // Quotes (organizer respond + convert)
    Route::get('quotes', [QuoteConversionController::class, 'index'])->name('quotes.index');
    Route::patch('quotes/{quote:uuid}/respond', [QuoteConversionController::class, 'respond'])->name('quotes.respond');
    Route::post('quotes/{quote:uuid}/convert', [QuoteConversionController::class, 'convert'])->name('quotes.convert');

    // Custom domains
    Route::get('domains', [OrganizationDomainController::class, 'index'])->name('domains.index');
    Route::post('domains', [OrganizationDomainController::class, 'store'])->name('domains.store');
    Route::post('domains/{domain}/verify', [OrganizationDomainController::class, 'verify'])->name('domains.verify');
    Route::delete('domains/{domain}', [OrganizationDomainController::class, 'destroy'])->name('domains.destroy');

    // Automation tokens (mint, list, revoke)
    Route::get('automation/tokens', [AutomationTokenController::class, 'index'])->name('automation.tokens.index');
    Route::post('automation/tokens', [AutomationTokenController::class, 'store'])->name('automation.tokens.store');
    Route::delete('automation/tokens/{token:uuid}', [AutomationTokenController::class, 'revoke'])->name('automation.tokens.revoke');

    // Outbound webhooks (configure, test-fire, rotate key)
    Route::get('automation/webhooks', [AutomationWebhookController::class, 'index'])->name('automation.webhooks.index');
    Route::post('automation/webhooks', [AutomationWebhookController::class, 'store'])->name('automation.webhooks.store');
    Route::patch('automation/webhooks/{webhook}', [AutomationWebhookController::class, 'update'])->name('automation.webhooks.update');
    Route::delete('automation/webhooks/{webhook}', [AutomationWebhookController::class, 'destroy'])->name('automation.webhooks.destroy');
    Route::post('automation/webhooks/{webhook}/test-fire', [AutomationWebhookController::class, 'testFire'])->name('automation.webhooks.test_fire');
    Route::post('automation/webhooks/{webhook}/rotate-key', [AutomationWebhookController::class, 'rotateKey'])->name('automation.webhooks.rotate_key');

    // Webhook delivery log + replay
    Route::get('automation/webhooks/{webhook}/deliveries', [WebhookDeliveryController::class, 'index'])->name('automation.webhooks.deliveries.index');
    Route::post('automation/webhooks/{webhook}/deliveries/{delivery}/replay', [WebhookDeliveryController::class, 'replay'])->name('automation.webhooks.deliveries.replay');

    // Extension marketplace — org installs + manages extensions.
    Route::get('extensions/installed', [\App\Http\Controllers\BackOffice\Extensions\ExtensionInstallController::class, 'installed'])
        ->name('extensions.installed');
    Route::post('extensions/{slug}/install', [\App\Http\Controllers\BackOffice\Extensions\ExtensionInstallController::class, 'install'])
        ->name('extensions.install');
    Route::post('extensions/installations/{installation:uuid}/disable', [\App\Http\Controllers\BackOffice\Extensions\ExtensionInstallController::class, 'disable'])
        ->name('extensions.disable');
    Route::post('extensions/installations/{installation:uuid}/reenable', [\App\Http\Controllers\BackOffice\Extensions\ExtensionInstallController::class, 'reenable'])
        ->name('extensions.reenable');
    Route::delete('extensions/installations/{installation:uuid}', [\App\Http\Controllers\BackOffice\Extensions\ExtensionInstallController::class, 'uninstall'])
        ->name('extensions.uninstall');
    Route::patch('extensions/installations/{installation:uuid}/config', [\App\Http\Controllers\BackOffice\Extensions\ExtensionInstallController::class, 'updateConfig'])
        ->name('extensions.config');
});
