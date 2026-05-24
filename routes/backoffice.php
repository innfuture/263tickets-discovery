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
| Every route ALSO carries a `permission:<key>` middleware enforcing
| the canonical permission catalogue in App\Enums\Permission. Org
| membership alone is no longer sufficient — staff must hold the
| relevant role.
|
| All routes return JSON; the Inertia dashboard pages consume them.
|
*/

Route::prefix('api/back-office')->name('back-office.')->group(function () {

    // ── Bundles ────────────────────────────────────────────────────────
    Route::get('bundles', [BundleManagementController::class, 'index'])
        ->middleware('permission:ticket_category.create')->name('bundles.index');
    Route::post('bundles', [BundleManagementController::class, 'store'])
        ->middleware('permission:ticket_category.create')->name('bundles.store');
    Route::patch('bundles/{bundle:slug}', [BundleManagementController::class, 'update'])
        ->middleware('permission:ticket_category.update')->name('bundles.update');
    Route::delete('bundles/{bundle:slug}', [BundleManagementController::class, 'destroy'])
        ->middleware('permission:ticket_category.delete')->name('bundles.destroy');

    // ── Addons (per-event) ─────────────────────────────────────────────
    Route::get('events/{event:slug}/addons', [AddonManagementController::class, 'index'])
        ->middleware('permission:event.view')->name('addons.index');
    Route::post('events/{event:slug}/addons', [AddonManagementController::class, 'store'])
        ->middleware('permission:event.update')->name('addons.store');
    Route::patch('addons/{addon:uuid}', [AddonManagementController::class, 'update'])
        ->middleware('permission:event.update')->name('addons.update');
    Route::delete('addons/{addon:uuid}', [AddonManagementController::class, 'destroy'])
        ->middleware('permission:event.update')->name('addons.destroy');

    // ── Per-event refund policy ────────────────────────────────────────
    Route::get('events/{event:slug}/refund-policy', [RefundPolicyController::class, 'show'])
        ->middleware('permission:event.view')->name('refund-policy.show');
    Route::put('events/{event:slug}/refund-policy', [RefundPolicyController::class, 'update'])
        ->middleware('permission:event.update')->name('refund-policy.update');

    // ── Approval queue ─────────────────────────────────────────────────
    Route::get('approvals', [ApprovalQueueController::class, 'index'])
        ->middleware('permission:order.view')->name('approvals.index');
    Route::post('approvals/{order:reference}/approve', [ApprovalQueueController::class, 'approve'])
        ->middleware('permission:order.manage')->name('approvals.approve');
    Route::post('approvals/{order:reference}/reject', [ApprovalQueueController::class, 'reject'])
        ->middleware('permission:order.manage')->name('approvals.reject');

    // ── Recurring event templates ──────────────────────────────────────
    Route::get('event-templates', [EventTemplateController::class, 'index'])
        ->middleware('permission:event.view')->name('event-templates.index');
    Route::post('event-templates', [EventTemplateController::class, 'store'])
        ->middleware('permission:event.create')->name('event-templates.store');
    Route::patch('event-templates/{template:uuid}', [EventTemplateController::class, 'update'])
        ->middleware('permission:event.update')->name('event-templates.update');
    Route::delete('event-templates/{template:uuid}', [EventTemplateController::class, 'destroy'])
        ->middleware('permission:event.delete')->name('event-templates.destroy');

    // ── Refund requests (review queue) ─────────────────────────────────
    Route::get('refund-requests', [RefundRequestReviewController::class, 'index'])
        ->middleware('permission:order.view')->name('refund-requests.index');
    Route::post('refund-requests/{refundRequest:uuid}/approve', [RefundRequestReviewController::class, 'approve'])
        ->middleware('permission:order.refund')->name('refund-requests.approve');
    Route::post('refund-requests/{refundRequest:uuid}/reject', [RefundRequestReviewController::class, 'reject'])
        ->middleware('permission:order.refund')->name('refund-requests.reject');

    // ── Quotes (corporate sales) ───────────────────────────────────────
    Route::get('quotes', [QuoteConversionController::class, 'index'])
        ->middleware('permission:order.view')->name('quotes.index');
    Route::patch('quotes/{quote:uuid}/respond', [QuoteConversionController::class, 'respond'])
        ->middleware('permission:order.manage')->name('quotes.respond');
    Route::post('quotes/{quote:uuid}/convert', [QuoteConversionController::class, 'convert'])
        ->middleware('permission:order.manage')->name('quotes.convert');

    // ── Custom domains ─────────────────────────────────────────────────
    Route::get('domains', [OrganizationDomainController::class, 'index'])
        ->middleware('permission:organization.manage-domain')->name('domains.index');
    Route::post('domains', [OrganizationDomainController::class, 'store'])
        ->middleware('permission:organization.manage-domain')->name('domains.store');
    Route::post('domains/{domain}/verify', [OrganizationDomainController::class, 'verify'])
        ->middleware('permission:organization.manage-domain')->name('domains.verify');
    Route::delete('domains/{domain}', [OrganizationDomainController::class, 'destroy'])
        ->middleware('permission:organization.manage-domain')->name('domains.destroy');

    // ── Automation tokens ──────────────────────────────────────────────
    Route::get('automation/tokens', [AutomationTokenController::class, 'index'])
        ->middleware('permission:api.manage-keys')->name('automation.tokens.index');
    Route::post('automation/tokens', [AutomationTokenController::class, 'store'])
        ->middleware('permission:api.manage-keys')->name('automation.tokens.store');
    Route::delete('automation/tokens/{token:uuid}', [AutomationTokenController::class, 'revoke'])
        ->middleware('permission:api.manage-keys')->name('automation.tokens.revoke');

    // ── Outbound webhooks ──────────────────────────────────────────────
    Route::get('automation/webhooks', [AutomationWebhookController::class, 'index'])
        ->middleware('permission:webhook.manage')->name('automation.webhooks.index');
    Route::post('automation/webhooks', [AutomationWebhookController::class, 'store'])
        ->middleware('permission:webhook.manage')->name('automation.webhooks.store');
    Route::patch('automation/webhooks/{webhook}', [AutomationWebhookController::class, 'update'])
        ->middleware('permission:webhook.manage')->name('automation.webhooks.update');
    Route::delete('automation/webhooks/{webhook}', [AutomationWebhookController::class, 'destroy'])
        ->middleware('permission:webhook.manage')->name('automation.webhooks.destroy');
    Route::post('automation/webhooks/{webhook}/test-fire', [AutomationWebhookController::class, 'testFire'])
        ->middleware('permission:webhook.manage')->name('automation.webhooks.test_fire');
    Route::post('automation/webhooks/{webhook}/rotate-key', [AutomationWebhookController::class, 'rotateKey'])
        ->middleware('permission:webhook.manage')->name('automation.webhooks.rotate_key');

    // ── Webhook delivery log + replay ──────────────────────────────────
    Route::get('automation/webhooks/{webhook}/deliveries', [WebhookDeliveryController::class, 'index'])
        ->middleware('permission:webhook.manage')->name('automation.webhooks.deliveries.index');
    Route::post('automation/webhooks/{webhook}/deliveries/{delivery}/replay', [WebhookDeliveryController::class, 'replay'])
        ->middleware('permission:webhook.manage')->name('automation.webhooks.deliveries.replay');

    // ── Stakeholder disputes (back-office review) ─────────────────────
    Route::get('disputes', [\App\Http\Controllers\BackOffice\Stakeholders\DisputeReviewController::class, 'index'])
        ->middleware('permission:event_sponsors.manage')->name('disputes.index');
    Route::post('engagements/{engagement:uuid}/disputes', [\App\Http\Controllers\BackOffice\Stakeholders\DisputeReviewController::class, 'openFromOrg'])
        ->middleware('permission:event_sponsors.manage')->name('disputes.open');
    Route::post('disputes/{dispute:uuid}/resolve', [\App\Http\Controllers\BackOffice\Stakeholders\DisputeReviewController::class, 'resolve'])
        ->middleware('permission:event_sponsors.manage')->name('disputes.resolve');

    // ── Collaborative event editor (Yjs server side) ──────────────────
    Route::get('events/{event:slug}/draft', [\App\Http\Controllers\BackOffice\Collaboration\EventDraftController::class, 'show'])
        ->middleware('permission:event.view')->name('events.draft.show');
    Route::post('events/{event:slug}/draft/apply', [\App\Http\Controllers\BackOffice\Collaboration\EventDraftController::class, 'apply'])
        ->middleware('permission:event.update')->name('events.draft.apply');
    Route::post('events/{event:slug}/draft/publish', [\App\Http\Controllers\BackOffice\Collaboration\EventDraftController::class, 'publish'])
        ->middleware('permission:event.update')->name('events.draft.publish');

    // ── AI organizer assistant ────────────────────────────────────────
    Route::post('ai/event-copy', [\App\Http\Controllers\BackOffice\Ai\AiAssistantController::class, 'eventCopy'])
        ->middleware('permission:event.update')->name('ai.event_copy');
    Route::post('ai/refund-reply', [\App\Http\Controllers\BackOffice\Ai\AiAssistantController::class, 'refundReply'])
        ->middleware('permission:order.refund')->name('ai.refund_reply');
    Route::post('ai/sales-insight', [\App\Http\Controllers\BackOffice\Ai\AiAssistantController::class, 'salesInsight'])
        ->middleware('permission:analytics.view-event')->name('ai.sales_insight');

    // ── Stakeholder Portal (back-office side) ─────────────────────────
    Route::get('stakeholders', [\App\Http\Controllers\BackOffice\Stakeholders\StakeholderEngagementBackOfficeController::class, 'stakeholders'])
        ->middleware('permission:event.view')->name('stakeholders.index');
    Route::post('events/{event:slug}/invitations', [\App\Http\Controllers\BackOffice\Stakeholders\StakeholderEngagementBackOfficeController::class, 'invite'])
        ->middleware('permission:event_sponsors.manage')->name('stakeholders.invite');
    Route::get('events/{event:slug}/applications', [\App\Http\Controllers\BackOffice\Stakeholders\StakeholderEngagementBackOfficeController::class, 'applications'])
        ->middleware('permission:event_sponsors.manage')->name('stakeholders.applications');
    Route::post('applications/{application:uuid}/approve', [\App\Http\Controllers\BackOffice\Stakeholders\StakeholderEngagementBackOfficeController::class, 'approveApplication'])
        ->middleware('permission:event_sponsors.manage')->name('stakeholders.applications.approve');
    Route::post('applications/{application:uuid}/decline', [\App\Http\Controllers\BackOffice\Stakeholders\StakeholderEngagementBackOfficeController::class, 'declineApplication'])
        ->middleware('permission:event_sponsors.manage')->name('stakeholders.applications.decline');
    Route::get('engagements', [\App\Http\Controllers\BackOffice\Stakeholders\StakeholderEngagementBackOfficeController::class, 'engagements'])
        ->middleware('permission:event.view')->name('engagements.index');
    Route::post('engagements/{engagement:uuid}/complete', [\App\Http\Controllers\BackOffice\Stakeholders\StakeholderEngagementBackOfficeController::class, 'completeEngagement'])
        ->middleware('permission:event_sponsors.manage')->name('engagements.complete');
    Route::post('engagements/{engagement:uuid}/cancel', [\App\Http\Controllers\BackOffice\Stakeholders\StakeholderEngagementBackOfficeController::class, 'cancelEngagement'])
        ->middleware('permission:event_sponsors.manage')->name('engagements.cancel');
    Route::post('engagements/{engagement:uuid}/reviews', [\App\Http\Controllers\BackOffice\Stakeholders\StakeholderEngagementBackOfficeController::class, 'leaveReview'])
        ->middleware('permission:event_sponsors.manage')->name('engagements.reviews');
    Route::post('deliverables/{deliverable:uuid}/approve', [\App\Http\Controllers\BackOffice\Stakeholders\StakeholderEngagementBackOfficeController::class, 'approveDeliverable'])
        ->middleware('permission:event_sponsors.manage')->name('deliverables.approve');
    Route::post('deliverables/{deliverable:uuid}/reject', [\App\Http\Controllers\BackOffice\Stakeholders\StakeholderEngagementBackOfficeController::class, 'rejectDeliverable'])
        ->middleware('permission:event_sponsors.manage')->name('deliverables.reject');
    Route::post('payments/{payment:uuid}/mark-paid', [\App\Http\Controllers\BackOffice\Stakeholders\StakeholderEngagementBackOfficeController::class, 'markPaymentPaid'])
        ->middleware('permission:finance.process-refund')->name('payments.mark_paid');

    // ── Extension marketplace ──────────────────────────────────────────
    Route::get('extensions/installed', [\App\Http\Controllers\BackOffice\Extensions\ExtensionInstallController::class, 'installed'])
        ->middleware('permission:integration.manage')->name('extensions.installed');
    Route::post('extensions/{slug}/install', [\App\Http\Controllers\BackOffice\Extensions\ExtensionInstallController::class, 'install'])
        ->middleware('permission:integration.manage')->name('extensions.install');
    Route::post('extensions/installations/{installation:uuid}/disable', [\App\Http\Controllers\BackOffice\Extensions\ExtensionInstallController::class, 'disable'])
        ->middleware('permission:integration.manage')->name('extensions.disable');
    Route::post('extensions/installations/{installation:uuid}/reenable', [\App\Http\Controllers\BackOffice\Extensions\ExtensionInstallController::class, 'reenable'])
        ->middleware('permission:integration.manage')->name('extensions.reenable');
    Route::delete('extensions/installations/{installation:uuid}', [\App\Http\Controllers\BackOffice\Extensions\ExtensionInstallController::class, 'uninstall'])
        ->middleware('permission:integration.manage')->name('extensions.uninstall');
    Route::patch('extensions/installations/{installation:uuid}/config', [\App\Http\Controllers\BackOffice\Extensions\ExtensionInstallController::class, 'updateConfig'])
        ->middleware('permission:integration.manage')->name('extensions.config');
});
