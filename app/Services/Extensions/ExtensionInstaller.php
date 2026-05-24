<?php

declare(strict_types=1);

namespace App\Services\Extensions;

use App\Models\Extension;
use App\Models\ExtensionAuditLog;
use App\Models\ExtensionInstallation;
use App\Models\ExtensionVersion;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Per-org installation lifecycle. On install we mint a scoped API
 * key (`ext_…`) and return the plaintext — operator stores it in
 * the extension's own dashboard. Granted permissions are the
 * intersection of (manifest declared) ∩ (operator chosen).
 *
 * Uninstall is soft (status flip) so audit + delivery logs stay
 * intact. A separate purge job could hard-delete after retention.
 */
class ExtensionInstaller
{
    public function install(
        Organization $org,
        ExtensionVersion $version,
        User $installer,
        array $grantedPermissions,
        array $config = [],
    ): array {
        if ($version->status !== ExtensionVersion::STATUS_PUBLISHED) {
            throw new RuntimeException('Cannot install a version that is not published.');
        }

        // Permission intersection — never grant more than declared.
        $allowed = array_values(array_intersect(
            $grantedPermissions,
            (array) $version->declared_permissions,
        ));

        return DB::transaction(function () use ($org, $version, $installer, $allowed, $config) {
            $existing = ExtensionInstallation::query()
                ->where('organization_id', $org->id)
                ->where('extension_id', $version->extension_id)
                ->first();
            if ($existing && $existing->status === ExtensionInstallation::STATUS_ACTIVE) {
                throw new RuntimeException('This extension is already installed in this organization.');
            }

            $plaintext = ExtensionInstallation::KEY_PREFIX.Str::random(48);

            $install = $existing ?? new ExtensionInstallation;
            $install->fill([
                'extension_id' => $version->extension_id,
                'extension_version_id' => $version->id,
                'organization_id' => $org->id,
                'installed_by_user_id' => $installer->id,
                'api_key_hash' => hash('sha256', $plaintext),
                'api_key_prefix' => substr($plaintext, 0, 8),
                'granted_permissions' => $allowed,
                'config' => $config,
                'status' => ExtensionInstallation::STATUS_ACTIVE,
                'installed_at' => now(),
                'disabled_at' => null,
                'uninstalled_at' => null,
            ])->save();

            Extension::query()->where('id', $version->extension_id)
                ->increment('install_count');

            ExtensionAuditLog::create([
                'extension_installation_id' => $install->id,
                'action' => 'installed',
                'context' => [
                    'installer_user_id' => $installer->id,
                    'version' => $version->version,
                    'granted_permissions' => $allowed,
                ],
            ]);

            return [
                'installation' => $install->fresh(),
                'api_key' => $plaintext,
                'note' => 'Token shown once — store in the extension dashboard now.',
            ];
        });
    }

    public function disable(ExtensionInstallation $install): void
    {
        $install->forceFill([
            'status' => ExtensionInstallation::STATUS_DISABLED,
            'disabled_at' => now(),
        ])->save();

        ExtensionAuditLog::create([
            'extension_installation_id' => $install->id,
            'action' => 'disabled',
        ]);
    }

    public function reenable(ExtensionInstallation $install): void
    {
        $install->forceFill([
            'status' => ExtensionInstallation::STATUS_ACTIVE,
            'disabled_at' => null,
        ])->save();

        ExtensionAuditLog::create([
            'extension_installation_id' => $install->id,
            'action' => 'reenabled',
        ]);
    }

    public function uninstall(ExtensionInstallation $install): void
    {
        $install->forceFill([
            'status' => ExtensionInstallation::STATUS_UNINSTALLED,
            'uninstalled_at' => now(),
        ])->save();

        Extension::query()->where('id', $install->extension_id)
            ->decrement('install_count');

        ExtensionAuditLog::create([
            'extension_installation_id' => $install->id,
            'action' => 'uninstalled',
        ]);
    }
}
