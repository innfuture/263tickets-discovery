<?php

namespace App\Http\Controllers\Organizations;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\UpdateOrganizationMemberRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Org-level membership management. Sub-team membership is a separate
 * (lighter) surface — adding/removing on a team just touches the
 * team_members pivot.
 */
class OrganizationMemberController extends Controller
{
    public function update(UpdateOrganizationMemberRequest $request, Organization $organization, User $user): RedirectResponse
    {
        Gate::authorize('updateMember', $organization);

        $newRole = TeamRole::from($request->validated('role'));

        $organization->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail()
            ->update(['role' => $newRole]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member role updated.')]);

        return back();
    }

    public function destroy(Organization $organization, User $user): RedirectResponse
    {
        Gate::authorize('removeMember', $organization);

        abort_if($organization->owner()?->is($user), 403, __('The organization owner cannot be removed.'));

        $organization->memberships()
            ->where('user_id', $user->id)
            ->delete();

        // Removing the user from the org also clears any sub-team
        // assignments they had within it.
        $organization->teams()->each(function ($team) use ($user) {
            $team->memberships()->where('user_id', $user->id)->delete();
        });

        if ($user->isCurrentOrganization($organization)) {
            $user->switchOrganization($user->personalOrganization());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return back();
    }
}
