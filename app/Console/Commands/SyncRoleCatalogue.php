<?php

namespace App\Console\Commands;

use App\Services\RoleProvisioningService;
use Illuminate\Console\Command;

/**
 * Re-syncs the platform's permission + role catalogue.
 *
 * Run this after adding a Permission case to App\Enums\Permission or
 * changing the default permission set on a SystemRole — it idempotently
 * upserts every permission row and re-syncs every system role's
 * permission set for every existing organization. Custom roles are
 * left untouched.
 *
 * Safe to re-run.
 */
class SyncRoleCatalogue extends Command
{
    protected $signature = 'rbac:sync';

    protected $description = 'Sync the permission catalogue and re-provision system roles for every organization.';

    public function handle(RoleProvisioningService $svc): int
    {
        $this->info('Syncing permission catalogue + provisioning system roles per org…');
        $svc->syncCatalogue();
        $this->info('RBAC catalogue synced.');

        return self::SUCCESS;
    }
}
