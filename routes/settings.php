<?php

use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\Organizations\OrganizationInvitationController;
use App\Http\Controllers\Organizations\OrganizationMemberController;
use App\Http\Controllers\Organizations\OrganizationSettingsController;
use App\Http\Controllers\Organizations\RoleController;
use App\Http\Controllers\Settings\Account\ApiTokenController;
use App\Http\Controllers\Settings\Account\NotificationPreferencesController;
use App\Http\Controllers\Settings\Account\SecurityController;
use App\Http\Controllers\Settings\Account\SessionController;
use App\Http\Controllers\Settings\Appearance\DateFormatController;
use App\Http\Controllers\Settings\Appearance\LocaleController;
use App\Http\Controllers\Settings\Billing\InvoiceController;
use App\Http\Controllers\Settings\Billing\PaymentGatewayController;
use App\Http\Controllers\Settings\Billing\PaymentMethodController;
use App\Http\Controllers\Settings\Billing\PayoutController;
use App\Http\Controllers\Settings\Billing\PlanController;
use App\Http\Controllers\Settings\Billing\RefundPolicyController;
use App\Http\Controllers\Settings\Billing\TaxController;
use App\Http\Controllers\Settings\Data\AuditLogController;
use App\Http\Controllers\Settings\Data\ExportController as DataExportController;
use App\Http\Controllers\Settings\Data\GdprRequestController;
use App\Http\Controllers\Settings\Data\RetentionController;
use App\Http\Controllers\Settings\Developer\ApiKeyController;
use App\Http\Controllers\Settings\Developer\ApiLogController;
use App\Http\Controllers\Settings\Developer\WebhookController as DeveloperWebhookController;
use App\Http\Controllers\Settings\HelpController;
use App\Http\Controllers\Settings\Integrations\ConnectedController as IntegrationConnectedController;
use App\Http\Controllers\Settings\Integrations\IntegrationController;
use App\Http\Controllers\Settings\Integrations\OAuthAppController;
use App\Http\Controllers\Settings\Operations\CheckInController;
use App\Http\Controllers\Settings\Operations\EmailIdentityController;
use App\Http\Controllers\Settings\Operations\TicketTemplateController;
use App\Http\Controllers\Settings\Operations\WebhookController as OperationsWebhookController;
use App\Http\Controllers\Settings\Organization\BrandController;
use App\Http\Controllers\Settings\Organization\DomainController;
use App\Http\Controllers\Settings\Organization\PublicProfileController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\Team\InvitationController as TeamInvitationController;
use App\Http\Controllers\Settings\Team\MemberController as TeamMembersController;
use App\Http\Controllers\Teams\TeamController;
use App\Http\Controllers\Teams\TeamMemberController;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

Route::middleware([
    'auth',
    ValidateSessionWithWorkOS::class,
])->group(function () {
    // Default landing is the Organization tab — see HandleInertiaRequests
    // share() for the org context the layout reads from.
    Route::redirect('settings', '/settings/organization');

    // ── Profile + notifications + appearance (already real) ────────────
    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    // Help & support — docs, FAQ, contact form. Permission-free; every
    // signed-in user can ask for help.
    Route::get('settings/help', [HelpController::class, 'show'])->name('help.show');
    Route::post('settings/help', [HelpController::class, 'submit'])->name('help.submit');

    Route::get('settings/notifications', [NotificationPreferencesController::class, 'edit'])->name('account.notifications.edit');
    Route::patch('settings/notifications', [NotificationPreferencesController::class, 'update'])->name('account.notifications.update');

    Route::get('settings/appearance/locale', [LocaleController::class, 'edit'])->name('appearance.locale.edit');
    Route::post('settings/appearance/locale', [LocaleController::class, 'update'])->name('appearance.locale.update');

    Route::get('settings/operations/check-in', [CheckInController::class, 'edit'])->name('operations.check-in.edit');
    Route::post('settings/operations/check-in', [CheckInController::class, 'update'])->name('operations.check-in.update');

    // ── Organization-level CRUD (already real) ─────────────────────────
    Route::get('settings/organization', [OrganizationController::class, 'edit'])->name('organization.edit');
    Route::post('settings/organization', [OrganizationController::class, 'update'])->name('organization.update');

    Route::get('settings/organizations', [OrganizationSettingsController::class, 'index'])->name('organizations.index');
    Route::post('settings/organizations', [OrganizationSettingsController::class, 'store'])->name('organizations.store');
    Route::patch('settings/organizations/{organization:slug}', [OrganizationSettingsController::class, 'update'])->name('organizations.update');
    Route::delete('settings/organizations/{organization:slug}', [OrganizationSettingsController::class, 'destroy'])->name('organizations.destroy');
    Route::post('settings/organizations/{organization:slug}/switch', [OrganizationSettingsController::class, 'switch'])->name('organizations.switch');

    Route::patch('settings/organizations/{organization:slug}/members/{user}', [OrganizationMemberController::class, 'update'])->name('organizations.members.update');
    Route::delete('settings/organizations/{organization:slug}/members/{user}', [OrganizationMemberController::class, 'destroy'])->name('organizations.members.destroy');

    Route::post('settings/organizations/{organization:slug}/invitations', [OrganizationInvitationController::class, 'store'])->name('organizations.invitations.store');
    Route::delete('settings/organizations/{organization:slug}/invitations/{invitation}', [OrganizationInvitationController::class, 'destroy'])->name('organizations.invitations.destroy');

    // ── Roles & Permissions ────────────────────────────────────────────
    Route::get('settings/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::post('settings/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('settings/roles/{role}', [RoleController::class, 'show'])->name('roles.show');
    Route::patch('settings/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('settings/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

    // ── Sub-teams (existing) ───────────────────────────────────────────
    Route::get('settings/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::post('settings/teams', [TeamController::class, 'store'])->name('teams.store');
    Route::get('settings/teams/{team:slug}', [TeamController::class, 'edit'])->name('teams.edit');
    Route::patch('settings/teams/{team:slug}', [TeamController::class, 'update'])->name('teams.update');
    Route::delete('settings/teams/{team:slug}', [TeamController::class, 'destroy'])->name('teams.destroy');
    Route::post('settings/teams/{team:slug}/switch', [TeamController::class, 'switch'])->name('teams.switch');

    Route::patch('settings/teams/{team:slug}/members/{user}', [TeamMemberController::class, 'update'])->name('teams.members.update');
    Route::delete('settings/teams/{team:slug}/members/{user}', [TeamMemberController::class, 'destroy'])->name('teams.members.destroy');

    // ── Organization → brand / domain / public ─────────────────────────
    Route::get('settings/organization/brand', [BrandController::class, 'edit'])->name('organization.brand.edit');
    Route::post('settings/organization/brand', [BrandController::class, 'update'])->name('organization.brand.update');
    Route::post('settings/organization/brand/logo', [BrandController::class, 'uploadLogo'])->name('organization.brand.logo.upload');
    Route::delete('settings/organization/brand/logo/{variant}', [BrandController::class, 'deleteLogo'])->name('organization.brand.logo.delete');

    Route::get('settings/organization/domain', [DomainController::class, 'edit'])->name('organization.domain.edit');
    Route::post('settings/organization/domain', [DomainController::class, 'update'])->name('organization.domain.update');
    Route::post('settings/organization/domain/verify', [DomainController::class, 'verify'])->name('organization.domain.verify');
    Route::delete('settings/organization/domain', [DomainController::class, 'destroy'])->name('organization.domain.destroy');

    Route::get('settings/organization/public', [PublicProfileController::class, 'edit'])->name('organization.public.edit');
    Route::post('settings/organization/public', [PublicProfileController::class, 'update'])->name('organization.public.update');

    // ── Account: security / api-tokens / sessions ──────────────────────
    Route::get('settings/security', [SecurityController::class, 'edit'])->name('account.security.edit');
    Route::post('settings/security/two-factor', [SecurityController::class, 'enrolTwoFactor'])->name('account.security.two-factor.enrol');
    Route::post('settings/security/two-factor/confirm', [SecurityController::class, 'confirmTwoFactor'])->name('account.security.two-factor.confirm');
    Route::delete('settings/security/two-factor', [SecurityController::class, 'disableTwoFactor'])->name('account.security.two-factor.disable');
    Route::post('settings/security/two-factor/recovery-codes', [SecurityController::class, 'regenerateRecoveryCodes'])->name('account.security.two-factor.recovery');
    Route::post('settings/security/allowlist', [SecurityController::class, 'updateAllowlist'])->name('account.security.allowlist.update');

    Route::get('settings/api-tokens', [ApiTokenController::class, 'index'])->name('account.api-tokens.index');
    Route::post('settings/api-tokens', [ApiTokenController::class, 'store'])->name('account.api-tokens.store');
    Route::delete('settings/api-tokens/{token}', [ApiTokenController::class, 'destroy'])->name('account.api-tokens.destroy');

    Route::get('settings/sessions', [SessionController::class, 'index'])->name('account.sessions.index');
    Route::delete('settings/sessions/{session}', [SessionController::class, 'destroy'])->name('account.sessions.destroy');
    Route::delete('settings/sessions', [SessionController::class, 'destroyAll'])->name('account.sessions.destroy-all');

    // ── Operations: ticket templates / email identity / webhooks ───────
    Route::get('settings/operations/ticket-templates', [TicketTemplateController::class, 'edit'])->name('operations.ticket-templates.edit');
    Route::post('settings/operations/ticket-templates', [TicketTemplateController::class, 'update'])->name('operations.ticket-templates.update');

    Route::get('settings/operations/email-identity', [EmailIdentityController::class, 'edit'])->name('operations.email-identity.edit');
    Route::post('settings/operations/email-identity', [EmailIdentityController::class, 'update'])->name('operations.email-identity.update');
    Route::post('settings/operations/email-identity/verify', [EmailIdentityController::class, 'verify'])->name('operations.email-identity.verify');
    Route::delete('settings/operations/email-identity', [EmailIdentityController::class, 'destroy'])->name('operations.email-identity.destroy');

    Route::get('settings/operations/webhooks', [OperationsWebhookController::class, 'index'])->name('operations.webhooks.index');
    Route::post('settings/operations/webhooks', [OperationsWebhookController::class, 'store'])->name('operations.webhooks.store');
    Route::patch('settings/operations/webhooks/{webhook}', [OperationsWebhookController::class, 'update'])->name('operations.webhooks.update');
    Route::post('settings/operations/webhooks/{webhook}/rotate', [OperationsWebhookController::class, 'rotateSecret'])->name('operations.webhooks.rotate');
    Route::delete('settings/operations/webhooks/{webhook}', [OperationsWebhookController::class, 'destroy'])->name('operations.webhooks.destroy');

    // ── Billing ────────────────────────────────────────────────────────
    Route::get('settings/billing/plan', [PlanController::class, 'show'])->name('billing.plan.show');
    Route::post('settings/billing/plan', [PlanController::class, 'change'])->name('billing.plan.change');

    Route::get('settings/billing/methods', [PaymentMethodController::class, 'index'])->name('billing.methods.index');
    Route::post('settings/billing/methods', [PaymentMethodController::class, 'store'])->name('billing.methods.store');
    Route::post('settings/billing/methods/{method}/default', [PaymentMethodController::class, 'setDefault'])->name('billing.methods.default');
    Route::delete('settings/billing/methods/{method}', [PaymentMethodController::class, 'destroy'])->name('billing.methods.destroy');
    Route::post('settings/billing/contact', [PaymentMethodController::class, 'updateBillingDetails'])->name('billing.contact.update');

    Route::get('settings/billing/invoices', [InvoiceController::class, 'index'])->name('billing.invoices.index');
    Route::get('settings/billing/invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('billing.invoices.download');

    Route::get('settings/billing/gateways', [PaymentGatewayController::class, 'index'])->name('billing.gateways.index');
    Route::post('settings/billing/gateways', [PaymentGatewayController::class, 'update'])->name('billing.gateways.update');

    Route::get('settings/billing/payouts', [PayoutController::class, 'index'])->name('billing.payouts.index');
    Route::post('settings/billing/payouts/connect', [PayoutController::class, 'connect'])->name('billing.payouts.connect');
    Route::post('settings/billing/payouts/schedule', [PayoutController::class, 'updateSchedule'])->name('billing.payouts.schedule');
    Route::delete('settings/billing/payouts/connect', [PayoutController::class, 'disconnect'])->name('billing.payouts.disconnect');

    Route::get('settings/billing/taxes', [TaxController::class, 'edit'])->name('billing.taxes.edit');
    Route::post('settings/billing/taxes', [TaxController::class, 'update'])->name('billing.taxes.update');

    Route::get('settings/billing/refunds', [RefundPolicyController::class, 'index'])->name('billing.refunds.index');
    Route::post('settings/billing/refunds', [RefundPolicyController::class, 'store'])->name('billing.refunds.store');
    Route::patch('settings/billing/refunds/{policy}', [RefundPolicyController::class, 'update'])->name('billing.refunds.update');
    Route::delete('settings/billing/refunds/{policy}', [RefundPolicyController::class, 'destroy'])->name('billing.refunds.destroy');

    // ── Team listing ───────────────────────────────────────────────────
    Route::get('settings/team/members', [TeamMembersController::class, 'index'])->name('team.members.index');
    Route::post('settings/team/members/bulk-role', [TeamMembersController::class, 'bulkUpdateRole'])->name('team.members.bulk-role');

    Route::get('settings/team/invitations', [TeamInvitationController::class, 'index'])->name('team.invitations.index');
    Route::post('settings/team/invitations/{invitation}/resend', [TeamInvitationController::class, 'resend'])->name('team.invitations.resend');

    // ── Integrations ───────────────────────────────────────────────────
    Route::get('settings/integrations', [IntegrationController::class, 'index'])->name('integrations.index');
    Route::post('settings/integrations/{provider}/connect', [IntegrationController::class, 'connect'])->name('integrations.connect');

    Route::get('settings/integrations/connected', [IntegrationConnectedController::class, 'index'])->name('integrations.connected.index');
    Route::delete('settings/integrations/connected/{connection}', [IntegrationConnectedController::class, 'destroy'])->name('integrations.connected.destroy');

    Route::get('settings/integrations/oauth', [OAuthAppController::class, 'index'])->name('integrations.oauth.index');
    Route::post('settings/integrations/oauth', [OAuthAppController::class, 'store'])->name('integrations.oauth.store');
    Route::patch('settings/integrations/oauth/{oauth}', [OAuthAppController::class, 'update'])->name('integrations.oauth.update');
    Route::post('settings/integrations/oauth/{oauth}/rotate', [OAuthAppController::class, 'rotateSecret'])->name('integrations.oauth.rotate');
    Route::delete('settings/integrations/oauth/{oauth}', [OAuthAppController::class, 'destroy'])->name('integrations.oauth.destroy');

    // ── Data & Compliance ──────────────────────────────────────────────
    Route::get('settings/data/audit-log', [AuditLogController::class, 'index'])->name('data.audit-log.index');
    Route::get('settings/data/audit-log/export', [AuditLogController::class, 'export'])->name('data.audit-log.export');

    Route::get('settings/data/exports', [DataExportController::class, 'index'])->name('data.exports.index');
    Route::post('settings/data/exports', [DataExportController::class, 'store'])->name('data.exports.store');
    Route::get('settings/data/exports/{export}/download', [DataExportController::class, 'download'])->name('data.exports.download');

    Route::get('settings/data/gdpr', [GdprRequestController::class, 'index'])->name('data.gdpr.index');
    Route::post('settings/data/gdpr', [GdprRequestController::class, 'store'])->name('data.gdpr.store');
    Route::patch('settings/data/gdpr/{gdpr}', [GdprRequestController::class, 'update'])->name('data.gdpr.update');

    Route::get('settings/data/retention', [RetentionController::class, 'edit'])->name('data.retention.edit');
    Route::post('settings/data/retention', [RetentionController::class, 'update'])->name('data.retention.update');

    // ── Developer ──────────────────────────────────────────────────────
    Route::get('settings/developer/api-keys', [ApiKeyController::class, 'index'])->name('developer.api-keys.index');
    Route::post('settings/developer/api-keys', [ApiKeyController::class, 'store'])->name('developer.api-keys.store');
    Route::post('settings/developer/api-keys/{apiKey}/rotate', [ApiKeyController::class, 'rotate'])->name('developer.api-keys.rotate');
    Route::delete('settings/developer/api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])->name('developer.api-keys.destroy');

    Route::get('settings/developer/webhooks', [DeveloperWebhookController::class, 'index'])->name('developer.webhooks.index');
    Route::post('settings/developer/webhooks', [DeveloperWebhookController::class, 'store'])->name('developer.webhooks.store');
    Route::delete('settings/developer/webhooks/{webhook}', [DeveloperWebhookController::class, 'destroy'])->name('developer.webhooks.destroy');
    Route::post('settings/developer/webhooks/deliveries/{delivery}/replay', [DeveloperWebhookController::class, 'replay'])->name('developer.webhooks.replay');

    Route::get('settings/developer/logs', [ApiLogController::class, 'index'])->name('developer.logs.index');

    // ── Appearance: dates ──────────────────────────────────────────────
    Route::get('settings/appearance/dates', [DateFormatController::class, 'edit'])->name('appearance.dates.edit');
    Route::post('settings/appearance/dates', [DateFormatController::class, 'update'])->name('appearance.dates.update');
});
