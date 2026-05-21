<?php

namespace App\Actions\Organizations;

use App\Enums\SystemRole;
use App\Enums\TeamRole;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use App\Services\RoleProvisioningService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates an organization, registers the user as its owner, and spins
 * up a default "General" sub-team so the Organization → Teams →
 * Members hierarchy is populated from the first signup tick. The user
 * is added as Owner of both the org and the default team, and their
 * active context (current_organization_id / current_team_id) is set in
 * a single transaction.
 */
class CreateOrganization
{
    public function __construct(
        private RoleProvisioningService $provisioner,
        private PermissionRegistrar $registrar,
    ) {
    }

    public function handle(User $user, string $name, bool $isPersonal = false): Organization
    {
        return DB::transaction(function () use ($user, $name, $isPersonal) {
            $org = Organization::create([
                'name' => $name,
                'is_personal' => $isPersonal,
            ]);

            $org->memberships()->create([
                'user_id' => $user->id,
                'role' => TeamRole::Owner,
            ]);

            // Every org boots with a default sub-team. Keeps the
            // hierarchy invariant (org always has at least one team)
            // and gives the user a writable team-context immediately.
            $team = Team::create([
                'organization_id' => $org->id,
                'name' => 'General',
                'is_personal' => $isPersonal,
            ]);

            $team->memberships()->create([
                'user_id' => $user->id,
                'role' => TeamRole::Owner,
            ]);

            // RBAC: provision the system roles for this brand-new org
            // and attach the creator as Owner, scoped to this org.
            $this->provisioner->provisionForOrg($org);
            $this->registrar->setPermissionsTeamId($org->id);
            $user->syncRoles([$this->provisioner->systemRole($org, SystemRole::Owner)]);

            $user->switchOrganization($org);
            $user->switchTeam($team);

            return $org;
        });
    }
}
