<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Team;
use App\Models\User;

/**
 * Sub-team authorisation. Spatie permissions live on the parent org;
 * a sub-team check additionally asserts the team belongs to the
 * viewer's current org.
 */
class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::TeamView->value);
    }

    public function view(User $user, Team $team): bool
    {
        return $this->inCurrentOrg($user, $team)
            && $user->can(Permission::TeamView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::TeamCreate->value);
    }

    public function update(User $user, Team $team): bool
    {
        return $this->inCurrentOrg($user, $team)
            && $user->can(Permission::TeamUpdate->value);
    }

    public function delete(User $user, Team $team): bool
    {
        return ! $team->is_personal
            && $this->inCurrentOrg($user, $team)
            && $user->can(Permission::TeamDelete->value);
    }

    public function addMember(User $user, Team $team): bool
    {
        return $this->inCurrentOrg($user, $team)
            && $user->can(Permission::TeamManageMembers->value);
    }

    public function updateMember(User $user, Team $team): bool
    {
        return $this->addMember($user, $team);
    }

    public function removeMember(User $user, Team $team): bool
    {
        return $this->addMember($user, $team);
    }

    /**
     * Cross-org references are an error; the team has to live under
     * the viewer's currently-active organization.
     */
    private function inCurrentOrg(User $user, Team $team): bool
    {
        return $user->current_organization_id !== null
            && $team->organization_id === $user->current_organization_id;
    }
}
