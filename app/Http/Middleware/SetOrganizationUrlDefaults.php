<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Globally seeds two pieces of per-request state derived from the
 * viewer's current organization:
 *
 *   1. `current_organization` (+ legacy `organization` alias) URL
 *      defaults so `route()` calls don't have to repeat the slug.
 *   2. Spatie's permissions team-id so every `$user->can()` / role
 *      check downstream resolves against the rows in
 *      `model_has_roles` / `model_has_permissions` for that org.
 *
 * Setting (2) here — rather than only inside
 * `EnsureOrganizationMembership` — matters for `/settings/*` and
 * `/{org}` lookups that don't go through the org-prefix middleware
 * group; otherwise their `$user->can()` checks return false because
 * Spatie has no team scope yet and the user appears to hold no
 * roles.
 */
class SetOrganizationUrlDefaults
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($org = $request->user()?->currentOrganization) {
            URL::defaults([
                'current_organization' => $org->slug,
                'organization' => $org->slug,
            ]);

            app(PermissionRegistrar::class)
                ->setPermissionsTeamId($org->id);
        }

        return $next($request);
    }
}
