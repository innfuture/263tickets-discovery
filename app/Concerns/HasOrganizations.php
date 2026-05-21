<?php

namespace App\Concerns;

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Support\TeamPermissions;
use App\Support\UserOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/**
 * Top-of-hierarchy membership concern. Sits on the User model; pairs
 * with HasTeams which scopes the sub-team layer within the active org.
 *
 * Role semantics reuse the existing TeamRole/TeamPermission enums —
 * Owner/Admin/Member have the same meaning at both tiers, and
 * collapsing to one enum avoids parallel-enum drift.
 */
trait HasOrganizations
{
    /**
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members', 'user_id', 'organization_id')
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * @return HasManyThrough<Organization, OrganizationMembership, $this>
     */
    public function ownedOrganizations(): HasManyThrough
    {
        return $this->hasManyThrough(
            Organization::class,
            OrganizationMembership::class,
            'user_id',
            'id',
            'id',
            'organization_id',
        )->where('organization_members.role', TeamRole::Owner->value);
    }

    /**
     * @return HasMany<OrganizationMembership, $this>
     */
    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class, 'user_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    public function personalOrganization(): ?Organization
    {
        return $this->organizations()
            ->where('is_personal', true)
            ->first();
    }

    public function switchOrganization(Organization $org): bool
    {
        if (! $this->belongsToOrganization($org)) {
            return false;
        }

        $this->update(['current_organization_id' => $org->id]);
        $this->setRelation('currentOrganization', $org);

        URL::defaults(['current_organization' => $org->slug]);

        return true;
    }

    public function belongsToOrganization(Organization $org): bool
    {
        return $this->organizations()->where('organizations.id', $org->id)->exists();
    }

    public function isCurrentOrganization(Organization $org): bool
    {
        return $this->current_organization_id === $org->id;
    }

    public function ownsOrganization(Organization $org): bool
    {
        return $this->organizationRole($org) === TeamRole::Owner;
    }

    public function organizationRole(Organization $org): ?TeamRole
    {
        return $this->organizationMemberships()
            ->where('organization_id', $org->id)
            ->first()
            ?->role;
    }

    /**
     * @return Collection<int, UserOrganization>
     */
    public function toUserOrganizations(bool $includeCurrent = false): Collection
    {
        return $this->organizations()
            ->get()
            ->map(fn (Organization $org) => ! $includeCurrent && $this->isCurrentOrganization($org)
                ? null
                : $this->toUserOrganization($org))
            ->filter()
            ->values();
    }

    public function toUserOrganization(Organization $org): UserOrganization
    {
        $role = $this->organizationRole($org);

        return new UserOrganization(
            id: $org->id,
            uuid: $org->uuid,
            name: $org->name,
            slug: $org->slug,
            isPersonal: $org->is_personal,
            role: $role?->value,
            roleLabel: $role?->label(),
            logoUrl: $org->logoUrl(),
            isVerified: (bool) $org->is_verified,
            isCurrent: $this->isCurrentOrganization($org),
        );
    }

    public function toOrganizationPermissions(Organization $org): TeamPermissions
    {
        $role = $this->organizationRole($org);

        return new TeamPermissions(
            canUpdateTeam: $role?->hasPermission(TeamPermission::UpdateTeam) ?? false,
            canDeleteTeam: $role?->hasPermission(TeamPermission::DeleteTeam) ?? false,
            canAddMember: $role?->hasPermission(TeamPermission::AddMember) ?? false,
            canUpdateMember: $role?->hasPermission(TeamPermission::UpdateMember) ?? false,
            canRemoveMember: $role?->hasPermission(TeamPermission::RemoveMember) ?? false,
            canCreateInvitation: $role?->hasPermission(TeamPermission::CreateInvitation) ?? false,
            canCancelInvitation: $role?->hasPermission(TeamPermission::CancelInvitation) ?? false,
        );
    }

    public function hasOrganizationPermission(Organization $org, TeamPermission $permission): bool
    {
        return $this->organizationRole($org)?->hasPermission($permission) ?? false;
    }

    public function fallbackOrganization(?Organization $excluding = null): ?Organization
    {
        return $this->organizations()
            ->when($excluding, fn ($q) => $q->where('organizations.id', '!=', $excluding->id))
            ->orderByRaw('LOWER(organizations.name)')
            ->first();
    }
}
