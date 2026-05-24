<?php

namespace App\Http\Controllers\Settings\Team;

use App\Enums\TeamRole;
use App\Http\Controllers\Settings\SettingsController;
use App\Models\Team;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Org-wide membership view. Lists every member regardless of sub-team
 * with roles, sub-team membership, and a bulk role-change action.
 */
class MemberController extends SettingsController
{
    public function index(Request $request): Response
    {
        $org = $this->org($request, 'organization_member.view');

        $members = $org->members()
            ->orderBy('name')
            ->get()
            ->map(function (User $u) use ($org) {
                $membership = $u->organizationMemberships()
                    ->where('organization_id', $org->id)
                    ->first();
                $teamNames = Team::query()
                    ->whereHas('members', fn ($q) => $q->where('users.id', $u->id))
                    ->where('organization_id', $org->id)
                    ->pluck('name')
                    ->all();

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'avatar' => $u->avatar,
                    'role' => $membership?->role ?? TeamRole::Member->value,
                    'teams' => $teamNames,
                    'last_seen_at' => $this->lastSeenFor($u),
                ];
            });

        return Inertia::render('settings/team/members', [
            'members' => $members,
            'roles' => collect(TeamRole::cases())->map(fn ($r) => ['value' => $r->value, 'label' => ucfirst($r->value)])->values(),
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Members', 'href' => '/settings/team/members'],
            ],
        ]);
    }

    public function bulkUpdateRole(Request $request, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'organization_member.update-role');

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'role' => ['required', 'in:'.collect(TeamRole::cases())->pluck('value')->implode(',')],
        ]);

        $role = TeamRole::from($data['role']);

        DB::transaction(function () use ($org, $data, $role) {
            $org->memberships()
                ->whereIn('user_id', $data['user_ids'])
                ->where('role', '!=', TeamRole::Owner->value)
                ->update(['role' => $role->value]);
        });

        $audit->record(
            action: 'org.member.bulk_role_changed',
            organization: $org,
            user: $request->user(),
            after: ['user_ids' => $data['user_ids'], 'role' => $data['role']],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':n member role(s) updated.', ['n' => count($data['user_ids'])])]);

        return back();
    }

    /**
     * Cheap "last seen" derived from the most recent user_sessions row.
     */
    private function lastSeenFor(User $user): ?string
    {
        $ts = DB::table('user_sessions')
            ->where('user_id', $user->id)
            ->max('last_active_at');

        return $ts ? Carbon::parse($ts)->toIso8601String() : null;
    }
}
