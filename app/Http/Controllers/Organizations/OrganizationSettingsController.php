<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\DeleteOrganizationRequest;
use App\Http\Requests\Organizations\SaveOrganizationRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Org-level CRUD that lives under /settings/organizations — list,
 * create, name-rename, delete. The brand-profile editor (logo,
 * banner, address, social) lives in OrganizationController::edit and
 * is reached separately from the settings nav.
 */
class OrganizationSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/organizations', [
            'organizations' => $user->toUserOrganizations(includeCurrent: true),
        ]);
    }

    public function store(SaveOrganizationRequest $request, \App\Actions\Organizations\CreateOrganization $createOrganization): RedirectResponse
    {
        $createOrganization->handle($request->user(), $request->validated('name'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization created.')]);

        return to_route('organizations.index');
    }

    public function update(SaveOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        Gate::authorize('update', $organization);

        $organization = DB::transaction(function () use ($request, $organization) {
            $organization = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $organization->update(['name' => $request->validated('name')]);

            return $organization;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization renamed.')]);

        return back();
    }

    public function switch(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($request->user()->belongsToOrganization($organization), 403);

        $request->user()->switchOrganization($organization);

        return back();
    }

    public function destroy(DeleteOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $user = $request->user();
        $fallback = $user->isCurrentOrganization($organization)
            ? $user->fallbackOrganization($organization)
            : null;

        DB::transaction(function () use ($user, $organization) {
            // Anyone else with this org as their current one gets bounced
            // back to their personal org so they don't end up in a
            // dangling-pointer state after the delete cascades.
            User::where('current_organization_id', $organization->id)
                ->where('id', '!=', $user->id)
                ->each(fn (User $u) => $u->switchOrganization($u->personalOrganization()));

            $organization->invitations()->delete();
            // teams + team_members cascade through the FK; org_members
            // cascades via the organization FK as well.
            $organization->delete();
        });

        if ($fallback) {
            $user->switchOrganization($fallback);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization deleted.')]);

        return to_route('organizations.index');
    }
}
