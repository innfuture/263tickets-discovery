<?php

namespace App\Http\Middleware;

use App\Enums\TeamRole;
use App\Models\Organization;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the `{current_organization}` URL prefix. Resolves the slug to
 * an Organization, asserts the viewer is a member, swaps the user's
 * active org if they navigated to a different one, and (optionally)
 * enforces a minimum role.
 *
 * Replaces the older EnsureTeamMembership middleware that paired with
 * the pre-refactor `{current_team}` prefix.
 */
class EnsureOrganizationMembership
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $minimumRole = null): Response
    {
        [$user, $org] = [$request->user(), $this->organization($request)];

        abort_if(! $user || ! $org || ! $user->belongsToOrganization($org), 403);

        $this->ensureMemberHasRequiredRole($user, $org, $minimumRole);

        if ($request->route('current_organization') && ! $user->isCurrentOrganization($org)) {
            $user->switchOrganization($org);
        }

        // Tell Spatie which org we're acting in — every $user->can()
        // and $user->hasRole() call downstream resolves against this
        // org's rows in model_has_roles / model_has_permissions.
        app(\Spatie\Permission\PermissionRegistrar::class)
            ->setPermissionsTeamId($org->id);

        return $next($request);
    }

    protected function ensureMemberHasRequiredRole(User $user, Organization $org, ?string $minimumRole): void
    {
        if ($minimumRole === null) {
            return;
        }

        $role = $user->organizationRole($org);
        $required = TeamRole::tryFrom($minimumRole);

        abort_if(
            $required === null || $role === null || ! $role->isAtLeast($required),
            403,
        );
    }

    protected function organization(Request $request): ?Organization
    {
        $slug = $request->route('current_organization') ?? $request->route('organization');

        if (is_string($slug)) {
            return Organization::where('slug', $slug)->first();
        }

        return $slug instanceof Organization ? $slug : null;
    }
}
