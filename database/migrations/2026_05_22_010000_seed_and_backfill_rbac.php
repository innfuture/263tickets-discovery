<?php

use App\Enums\SystemRole;
use App\Enums\TeamRole;
use App\Models\Organization;
use App\Models\User;
use App\Services\RoleProvisioningService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Two-pass data migration:
 *
 *   1. Provision the permission + role catalogue for every existing
 *      organization (idempotent — runs `RoleProvisioningService::syncCatalogue`).
 *   2. Backfill each `organization_members` row into a Spatie role
 *      assignment, mapping the legacy enum (Owner/Admin/Member) to the
 *      equivalent system role.
 *
 * The legacy `organization_members.role` column stays on the table as a
 * fast denormalised lookup but Spatie is now the authoritative source.
 *
 * Safe to re-run: provisioning is upsert-only and role attachments are
 * idempotent (Spatie's `syncRoles` replaces, doesn't append).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Spatie caches permissions on first read. Flush so the freshly
        // migrated columns (group/label, organization_id) are seen.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        app(RoleProvisioningService::class)->syncCatalogue();

        // Backfill: for every org membership, attach the equivalent role.
        DB::table('organization_members')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $org = Organization::find($row->organization_id);
                    $user = User::find($row->user_id);
                    if (! $org || ! $user) {
                        continue;
                    }

                    $systemRole = $this->mapLegacyRole($row->role);
                    $role = app(RoleProvisioningService::class)->systemRole($org, $systemRole);

                    // Scope Spatie to this org before assigning so the
                    // role attaches to (user, org), not globally.
                    app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);

                    $user->syncRoles([$role]);
                }
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Pure data migration — the up() is replayable, the down() is
        // a no-op rather than tearing out role assignments that may
        // have been edited from the UI since.
    }

    private function mapLegacyRole(?string $legacy): SystemRole
    {
        return match (TeamRole::tryFrom((string) $legacy)) {
            TeamRole::Owner => SystemRole::Owner,
            TeamRole::Admin => SystemRole::Admin,
            default => SystemRole::Member,
        };
    }
};
