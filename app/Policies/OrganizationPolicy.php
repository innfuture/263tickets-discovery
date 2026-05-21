<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization, TeamPermission::UpdateTeam);
    }

    public function addMember(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization, TeamPermission::AddMember);
    }

    public function updateMember(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization, TeamPermission::UpdateMember);
    }

    public function removeMember(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization, TeamPermission::RemoveMember);
    }

    public function inviteMember(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization, TeamPermission::CreateInvitation);
    }

    public function cancelInvitation(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization, TeamPermission::CancelInvitation);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return ! $organization->is_personal
            && $user->hasOrganizationPermission($organization, TeamPermission::DeleteTeam);
    }
}
