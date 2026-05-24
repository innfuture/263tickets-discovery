<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ExtensionInstallation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Authorization: Bearer ext_…` lookup. Resolves the installation,
 * checks the required permission, attaches the installation +
 * organization to the request.
 *
 * Usage: `middleware('extension.installation:orders.read')`
 */
class EnsureExtensionInstallation
{
    public function handle(Request $request, Closure $next, ?string $requiredPermission = null): Response
    {
        $bearer = $request->bearerToken();
        if (! $bearer || ! str_starts_with($bearer, ExtensionInstallation::KEY_PREFIX)) {
            return response()->json(['error' => 'missing_token'], 401);
        }

        $install = ExtensionInstallation::query()
            ->where('api_key_hash', hash('sha256', $bearer))
            ->with('organization', 'extension', 'version')
            ->first();

        if (! $install || ! $install->isActive()) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        if ($requiredPermission && ! $install->hasPermission($requiredPermission)) {
            return response()->json([
                'error' => 'insufficient_permission',
                'required' => $requiredPermission,
                'granted' => $install->granted_permissions,
            ], 403);
        }

        $request->attributes->set('extension_installation', $install);
        $request->attributes->set('extension_organization', $install->organization);

        return $next($request);
    }
}
