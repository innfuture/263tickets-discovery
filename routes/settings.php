<?php

use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\Organizations\OrganizationInvitationController;
use App\Http\Controllers\Organizations\OrganizationMemberController;
use App\Http\Controllers\Organizations\OrganizationSettingsController;
use App\Http\Controllers\Organizations\RoleController;
use App\Http\Controllers\Settings\Account\NotificationPreferencesController;
use App\Http\Controllers\Settings\Appearance\LocaleController;
use App\Http\Controllers\Settings\Operations\CheckInController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\ScaffoldController;
use App\Http\Controllers\Teams\TeamController;
use App\Http\Controllers\Teams\TeamMemberController;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

Route::middleware([
    'auth',
    ValidateSessionWithWorkOS::class,
])->group(function () {
    // The settings sidebar defaults users into the Organization tab so
    // the parent entity is always visible first — matches the
    // Organization → Teams → Members order called out in the spec.
    Route::redirect('settings', '/settings/organization');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    // ── Organization (org-profile editing + org-level membership) ─────
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

    // ── Roles &amp; Permissions (org-scoped RBAC catalogue) ──────────────
    Route::get('settings/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::post('settings/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('settings/roles/{role}', [RoleController::class, 'show'])->name('roles.show');
    Route::patch('settings/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('settings/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

    // ── Teams (sub-teams within the viewer's current organization) ────
    Route::get('settings/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::post('settings/teams', [TeamController::class, 'store'])->name('teams.store');
    Route::get('settings/teams/{team:slug}', [TeamController::class, 'edit'])->name('teams.edit');
    Route::patch('settings/teams/{team:slug}', [TeamController::class, 'update'])->name('teams.update');
    Route::delete('settings/teams/{team:slug}', [TeamController::class, 'destroy'])->name('teams.destroy');
    Route::post('settings/teams/{team:slug}/switch', [TeamController::class, 'switch'])->name('teams.switch');

    Route::patch('settings/teams/{team:slug}/members/{user}', [TeamMemberController::class, 'update'])->name('teams.members.update');
    Route::delete('settings/teams/{team:slug}/members/{user}', [TeamMemberController::class, 'destroy'])->name('teams.members.destroy');

    // ── Real-form pages ──────────────────────────────────────────────
    // Persisted state — small, low-risk: notification toggles, door
    // PIN, locale defaults. Each has its own controller; their pages
    // are written by hand, not through the ScaffoldController.
    Route::get('settings/notifications', [NotificationPreferencesController::class, 'edit'])->name('account.notifications.edit');
    Route::patch('settings/notifications', [NotificationPreferencesController::class, 'update'])->name('account.notifications.update');

    Route::get('settings/operations/check-in', [CheckInController::class, 'edit'])->name('operations.check-in.edit');
    Route::post('settings/operations/check-in', [CheckInController::class, 'update'])->name('operations.check-in.update');

    Route::get('settings/appearance/locale', [LocaleController::class, 'edit'])->name('appearance.locale.edit');
    Route::post('settings/appearance/locale', [LocaleController::class, 'update'])->name('appearance.locale.update');

    // ── Scaffolded pages ─────────────────────────────────────────────
    // Every URL below routes through a single ScaffoldController whose
    // page-name argument keys into a permission-gated catalogue of
    // "future" pages. As each page graduates to a real implementation
    // its line moves up to a dedicated controller above and the
    // catalogue entry is deleted.
    Route::get('settings/organization/brand', fn () => app(ScaffoldController::class)->show(request(), 'organization.brand'))->name('settings.scaffold.organization.brand');
    Route::get('settings/organization/domain', fn () => app(ScaffoldController::class)->show(request(), 'organization.domain'))->name('settings.scaffold.organization.domain');
    Route::get('settings/organization/public', fn () => app(ScaffoldController::class)->show(request(), 'organization.public'))->name('settings.scaffold.organization.public');

    Route::get('settings/security', fn () => app(ScaffoldController::class)->show(request(), 'account.security'))->name('settings.scaffold.account.security');
    Route::get('settings/api-tokens', fn () => app(ScaffoldController::class)->show(request(), 'account.api-tokens'))->name('settings.scaffold.account.api-tokens');
    Route::get('settings/sessions', fn () => app(ScaffoldController::class)->show(request(), 'account.sessions'))->name('settings.scaffold.account.sessions');

    Route::get('settings/operations/ticket-templates', fn () => app(ScaffoldController::class)->show(request(), 'operations.ticket-templates'))->name('settings.scaffold.operations.ticket-templates');
    Route::get('settings/operations/email-identity', fn () => app(ScaffoldController::class)->show(request(), 'operations.email-identity'))->name('settings.scaffold.operations.email-identity');
    Route::get('settings/operations/webhooks', fn () => app(ScaffoldController::class)->show(request(), 'operations.webhooks'))->name('settings.scaffold.operations.webhooks');

    Route::get('settings/billing/plan', fn () => app(ScaffoldController::class)->show(request(), 'billing.plan'))->name('settings.scaffold.billing.plan');
    Route::get('settings/billing/methods', fn () => app(ScaffoldController::class)->show(request(), 'billing.methods'))->name('settings.scaffold.billing.methods');
    Route::get('settings/billing/invoices', fn () => app(ScaffoldController::class)->show(request(), 'billing.invoices'))->name('settings.scaffold.billing.invoices');
    Route::get('settings/billing/payouts', fn () => app(ScaffoldController::class)->show(request(), 'billing.payouts'))->name('settings.scaffold.billing.payouts');
    Route::get('settings/billing/taxes', fn () => app(ScaffoldController::class)->show(request(), 'billing.taxes'))->name('settings.scaffold.billing.taxes');
    Route::get('settings/billing/refunds', fn () => app(ScaffoldController::class)->show(request(), 'billing.refunds'))->name('settings.scaffold.billing.refunds');

    Route::get('settings/team/members', fn () => app(ScaffoldController::class)->show(request(), 'team.members'))->name('settings.scaffold.team.members');
    Route::get('settings/team/invitations', fn () => app(ScaffoldController::class)->show(request(), 'team.invitations'))->name('settings.scaffold.team.invitations');

    Route::get('settings/integrations', fn () => app(ScaffoldController::class)->show(request(), 'integrations.index'))->name('settings.scaffold.integrations.index');
    Route::get('settings/integrations/connected', fn () => app(ScaffoldController::class)->show(request(), 'integrations.connected'))->name('settings.scaffold.integrations.connected');
    Route::get('settings/integrations/oauth', fn () => app(ScaffoldController::class)->show(request(), 'integrations.oauth'))->name('settings.scaffold.integrations.oauth');

    Route::get('settings/data/audit-log', fn () => app(ScaffoldController::class)->show(request(), 'data.audit-log'))->name('settings.scaffold.data.audit-log');
    Route::get('settings/data/exports', fn () => app(ScaffoldController::class)->show(request(), 'data.exports'))->name('settings.scaffold.data.exports');
    Route::get('settings/data/gdpr', fn () => app(ScaffoldController::class)->show(request(), 'data.gdpr'))->name('settings.scaffold.data.gdpr');
    Route::get('settings/data/retention', fn () => app(ScaffoldController::class)->show(request(), 'data.retention'))->name('settings.scaffold.data.retention');

    Route::get('settings/developer/api-keys', fn () => app(ScaffoldController::class)->show(request(), 'developer.api-keys'))->name('settings.scaffold.developer.api-keys');
    Route::get('settings/developer/webhooks', fn () => app(ScaffoldController::class)->show(request(), 'developer.webhooks'))->name('settings.scaffold.developer.webhooks');
    Route::get('settings/developer/logs', fn () => app(ScaffoldController::class)->show(request(), 'developer.logs'))->name('settings.scaffold.developer.logs');

    Route::get('settings/appearance/dates', fn () => app(ScaffoldController::class)->show(request(), 'appearance.dates'))->name('settings.scaffold.appearance.dates');
});
