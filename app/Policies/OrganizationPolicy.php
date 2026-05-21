<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Organization;
use App\Models\User;

/**
 * Org-level authorisation. Every method routes through Spatie's
 * `$user->can()` against permissions defined in App\Enums\Permission.
 * Role assignments are scoped per-org by `EnsureOrganizationMembership`
 * via `setPermissionsTeamId()`.
 */
class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization)
            && $user->can(Permission::OrganizationView->value);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization)
            && $user->can(Permission::OrganizationUpdate->value);
    }

    public function addMember(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization)
            && $user->can(Permission::OrganizationMemberInvite->value);
    }

    public function updateMember(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization)
            && $user->can(Permission::OrganizationMemberUpdateRole->value);
    }

    public function removeMember(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization)
            && $user->can(Permission::OrganizationMemberRemove->value);
    }

    public function inviteMember(User $user, Organization $organization): bool
    {
        return $this->addMember($user, $organization);
    }

    public function cancelInvitation(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization)
            && $user->can(Permission::OrganizationMemberRemove->value);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return ! $organization->is_personal
            && $user->belongsToOrganization($organization)
            && $user->can(Permission::OrganizationDelete->value);
    }

    public function manageRoles(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization)
            && $user->can(Permission::RoleManage->value);
    }

    public function viewRoles(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization)
            && $user->can(Permission::RoleView->value);
    }
}
