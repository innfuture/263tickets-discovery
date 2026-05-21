<?php

use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\Organizations\OrganizationInvitationController;
use App\Http\Controllers\Organizations\OrganizationMemberController;
use App\Http\Controllers\Organizations\OrganizationSettingsController;
use App\Http\Controllers\Settings\ProfileController;
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

    // ── Teams (sub-teams within the viewer's current organization) ────
    Route::get('settings/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::post('settings/teams', [TeamController::class, 'store'])->name('teams.store');
    Route::get('settings/teams/{team:slug}', [TeamController::class, 'edit'])->name('teams.edit');
    Route::patch('settings/teams/{team:slug}', [TeamController::class, 'update'])->name('teams.update');
    Route::delete('settings/teams/{team:slug}', [TeamController::class, 'destroy'])->name('teams.destroy');
    Route::post('settings/teams/{team:slug}/switch', [TeamController::class, 'switch'])->name('teams.switch');

    Route::patch('settings/teams/{team:slug}/members/{user}', [TeamMemberController::class, 'update'])->name('teams.members.update');
    Route::delete('settings/teams/{team:slug}/members/{user}', [TeamMemberController::class, 'destroy'])->name('teams.members.destroy');
});
