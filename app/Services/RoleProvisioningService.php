<?php

namespace App\Services;

use App\Enums\Permission as PermissionEnum;
use App\Enums\SystemRole;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Provisions the platform's permission + role catalogue.
 *
 * The catalogue lives in two enums (Permission, SystemRole). This
 * service is the gate that turns those enums into actual `permissions`
 * and `roles` rows — global permissions (org-agnostic) plus a
 * per-organization copy of every system role.
 *
 * Idempotent in both directions:
 *   - syncCatalogue()  walks the enums and upserts missing rows.
 *   - provisionForOrg() runs the same for a single org (called from
 *     CreateOrganization right after the org row is inserted).
 *
 * Use this whenever the Permission enum gains a new case or when a
 * SystemRole's default permission set changes.
 */
class RoleProvisioningService
{
    public function __construct(private PermissionRegistrar $registrar)
    {
    }

    /**
     * Full re-sync: ensure every Permission case has a global row, and
     * every Organization has every SystemRole with the current default
     * permission set.
     */
    public function syncCatalogue(): void
    {
        $this->syncPermissions();

        Organization::query()->each(function (Organization $org) {
            $this->provisionForOrg($org);
        });

        $this->registrar->forgetCachedPermissions();
    }

    /**
     * Materialise (or update) every system role for one organization.
     * Custom roles created via the UI are left untouched.
     */
    public function provisionForOrg(Organization $org): void
    {
        $this->syncPermissions();

        DB::transaction(function () use ($org) {
            foreach (SystemRole::all() as $systemRole) {
                $role = Role::query()->updateOrCreate(
                    [
                        'name' => $systemRole->value,
                        'organization_id' => $org->id,
                        'guard_name' => 'web',
                    ],
                    [
                        'level' => $systemRole->level(),
                        'is_system' => true,
                        'description' => $systemRole->description(),
                    ],
                );

                $role->syncPermissions(
                    array_map(fn (PermissionEnum $p) => $p->value, $systemRole->permissions()),
                );
            }
        });

        $this->registrar->forgetCachedPermissions();
    }

    /**
     * Upsert every Permission case as a global (non-team) row. The
     * `group` and `label` columns are kept in sync with the enum so the
     * UI never falls out of date.
     */
    private function syncPermissions(): void
    {
        foreach (PermissionEnum::all() as $perm) {
            Permission::query()->updateOrCreate(
                ['name' => $perm->value, 'guard_name' => 'web'],
                ['group' => $perm->group(), 'label' => $perm->label()],
            );
        }
    }

    /**
     * Resolve the org-scoped Role row for a SystemRole. Used by the
     * backfill seeder to map the legacy `organization_members.role`
     * enum into Spatie role assignments.
     */
    public function systemRole(Organization $org, SystemRole $role): Role
    {
        return Role::query()
            ->where('name', $role->value)
            ->where('organization_id', $org->id)
            ->where('guard_name', 'web')
            ->firstOrFail();
    }
}
