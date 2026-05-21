<?php

namespace App\Http\Controllers\Teams;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\DeleteTeamRequest;
use App\Http\Requests\Teams\SaveTeamRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sub-team CRUD within the viewer's currentOrganization. Operates on
 * Team rows scoped to `current_organization_id` — anything else is a
 * cross-org reference and 404s.
 */
class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $org = $user->currentOrganization;

        abort_if($org === null, 404);

        $teams = $org->teams()
            ->orderByRaw('LOWER(name)')
            ->get()
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'slug' => $team->slug,
                'isPersonal' => (bool) $team->is_personal,
                'isCurrent' => $user->isCurrentTeam($team),
                'memberCount' => $team->members()->count(),
            ]);

        return Inertia::render('settings/teams', [
            'teams' => $teams,
        ]);
    }

    public function store(SaveTeamRequest $request): RedirectResponse
    {
        $user = $request->user();
        $org = $user->currentOrganization;

        abort_if($org === null, 404);

        $team = DB::transaction(function () use ($org, $request, $user) {
            $team = $org->teams()->create([
                'name' => $request->validated('name'),
                'description' => $request->validated('description'),
                'is_personal' => false,
            ]);

            $team->memberships()->create([
                'user_id' => $user->id,
                'role' => TeamRole::Owner,
            ]);

            return $team;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team created.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    public function edit(Request $request, Team $team): Response
    {
        $user = $request->user();
        $this->assertTeamInCurrentOrg($team, $user);

        return Inertia::render('settings/team-edit', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'slug' => $team->slug,
                'isPersonal' => $team->is_personal,
            ],
            'members' => $team->members()->get()->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'avatar' => $member->avatar ?? null,
                'role' => $member->pivot->role->value,
                'role_label' => $member->pivot->role->label(),
            ]),
            'permissions' => $user->toTeamPermissions($team),
            'availableRoles' => TeamRole::assignable(),
        ]);
    }

    public function update(SaveTeamRequest $request, Team $team): RedirectResponse
    {
        $this->assertTeamInCurrentOrg($team, $request->user());

        Gate::authorize('update', $team);

        $team = DB::transaction(function () use ($request, $team) {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();
            $team->update([
                'name' => $request->validated('name'),
                'description' => $request->validated('description'),
            ]);

            return $team;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team updated.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    public function switch(Request $request, Team $team): RedirectResponse
    {
        $this->assertTeamInCurrentOrg($team, $request->user());
        abort_unless($request->user()->belongsToTeam($team), 403);

        $request->user()->switchTeam($team);

        return back();
    }

    public function destroy(DeleteTeamRequest $request, Team $team): RedirectResponse
    {
        $user = $request->user();
        $this->assertTeamInCurrentOrg($team, $user);

        abort_if($team->is_personal, 403, __('Personal teams cannot be deleted.'));

        $fallback = $user->isCurrentTeam($team)
            ? $user->fallbackTeam($team)
            : null;

        DB::transaction(function () use ($team) {
            $team->memberships()->delete();
            $team->delete();
        });

        if ($fallback) {
            $user->switchTeam($fallback);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team deleted.')]);

        return to_route('teams.index');
    }

    /**
     * Guard against cross-org references: a team's organization_id must
     * match the viewer's current_organization_id.
     */
    private function assertTeamInCurrentOrg(Team $team, $user): void
    {
        abort_unless(
            $user !== null
                && $user->current_organization_id !== null
                && $team->organization_id === $user->current_organization_id,
            404,
        );
    }
}
