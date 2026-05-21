<?php

namespace App\Http\Controllers\Organizations;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\AcceptOrganizationInvitationRequest;
use App\Http\Requests\Organizations\CreateOrganizationInvitationRequest;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Notifications\Organizations\OrganizationInvitation as OrganizationInvitationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

/**
 * Org-level invitations. Sub-team membership has no separate
 * invitation surface — once in the org, an admin can assign existing
 * members to teams from the team settings page.
 */
class OrganizationInvitationController extends Controller
{
    public function store(CreateOrganizationInvitationRequest $request, Organization $organization): RedirectResponse
    {
        Gate::authorize('inviteMember', $organization);

        $invitation = $organization->invitations()->create([
            'email' => $request->validated('email'),
            'role' => TeamRole::from($request->validated('role')),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(3),
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new OrganizationInvitationNotification($invitation));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

        return back();
    }

    public function destroy(Organization $organization, OrganizationInvitation $invitation): RedirectResponse
    {
        abort_unless($invitation->organization_id === $organization->id, 404);

        Gate::authorize('cancelInvitation', $organization);

        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation cancelled.')]);

        return back();
    }

    public function accept(AcceptOrganizationInvitationRequest $request, OrganizationInvitation $invitation): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $invitation) {
            $org = $invitation->organization;

            $org->memberships()->firstOrCreate(
                ['user_id' => $user->id],
                ['role' => $invitation->role],
            );

            $invitation->update(['accepted_at' => now()]);

            $user->switchOrganization($org);
        });

        return to_route('dashboard');
    }
}
