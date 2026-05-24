<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Extensions;

use App\Http\Controllers\Controller;
use App\Models\Extension;
use App\Models\ExtensionInstallation;
use App\Models\ExtensionVersion;
use App\Models\Organization;
use App\Services\Extensions\ExtensionInstaller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Organizer-side install + lifecycle for marketplace extensions.
 *
 *   GET    /api/back-office/extensions/installed
 *   POST   /api/back-office/extensions/{slug}/install
 *   POST   /api/back-office/extensions/installations/{uuid}/disable
 *   POST   /api/back-office/extensions/installations/{uuid}/reenable
 *   DELETE /api/back-office/extensions/installations/{uuid}
 *   PATCH  /api/back-office/extensions/installations/{uuid}/config
 */
class ExtensionInstallController extends Controller
{
    public function __construct(protected ExtensionInstaller $installer) {}

    public function installed(Organization $currentOrganization): JsonResponse
    {
        return response()->json([
            'data' => ExtensionInstallation::query()
                ->where('organization_id', $currentOrganization->id)
                ->where('status', '!=', ExtensionInstallation::STATUS_UNINSTALLED)
                ->with('extension:id,slug,name,icon_url', 'version:id,version')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function install(Organization $currentOrganization, string $slug, Request $request): JsonResponse
    {
        $extension = Extension::query()
            ->where('slug', $slug)
            ->where('status', Extension::STATUS_APPROVED)
            ->first();
        if (! $extension) {
            return response()->json(['error' => 'extension_not_found'], 404);
        }

        $version = $extension->versions()
            ->where('status', ExtensionVersion::STATUS_PUBLISHED)
            ->orderByDesc('published_at')
            ->first();
        if (! $version) {
            return response()->json(['error' => 'no_published_version'], 422);
        }

        $validated = $request->validate([
            'granted_permissions' => ['required', 'array'],
            'granted_permissions.*' => ['string'],
            'config' => ['nullable', 'array'],
        ]);

        try {
            $result = $this->installer->install(
                org: $currentOrganization,
                version: $version,
                installer: $request->user(),
                grantedPermissions: $validated['granted_permissions'],
                config: $validated['config'] ?? [],
            );
        } catch (RuntimeException $e) {
            return response()->json(['error' => 'install_failed', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'installation_uuid' => $result['installation']->uuid,
                'api_key' => $result['api_key'],
                'note' => $result['note'],
                'granted_permissions' => $result['installation']->granted_permissions,
            ],
        ], 201);
    }

    public function disable(Organization $currentOrganization, ExtensionInstallation $installation): JsonResponse
    {
        abort_if($installation->organization_id !== $currentOrganization->id, 404);
        $this->installer->disable($installation);

        return response()->json(['data' => ['status' => $installation->fresh()->status]]);
    }

    public function reenable(Organization $currentOrganization, ExtensionInstallation $installation): JsonResponse
    {
        abort_if($installation->organization_id !== $currentOrganization->id, 404);
        $this->installer->reenable($installation);

        return response()->json(['data' => ['status' => $installation->fresh()->status]]);
    }

    public function uninstall(Organization $currentOrganization, ExtensionInstallation $installation): JsonResponse
    {
        abort_if($installation->organization_id !== $currentOrganization->id, 404);
        $this->installer->uninstall($installation);

        return response()->json(['data' => ['status' => $installation->fresh()->status]]);
    }

    public function updateConfig(Organization $currentOrganization, ExtensionInstallation $installation, Request $request): JsonResponse
    {
        abort_if($installation->organization_id !== $currentOrganization->id, 404);

        $validated = $request->validate([
            'config' => ['required', 'array'],
        ]);

        $installation->forceFill(['config' => $validated['config']])->save();

        return response()->json(['data' => ['config' => $installation->fresh()->config]]);
    }
}
