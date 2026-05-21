<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',

            // Top-of-hierarchy: the org the viewer is acting on.
            // Drives the URL prefix, the public profile link, and the
            // /settings/organization page.
            'currentOrganization' => fn () => $user?->currentOrganization
                ? $user->toUserOrganization($user->currentOrganization)
                : null,
            'organizations' => fn () => $user?->toUserOrganizations(includeCurrent: true) ?? [],

            // Inner tier: the sub-team active within the current org.
            // Some pages still depend on team-scoped context (e.g.
            // attribution on a created resource); keep both layers
            // shipped so frontend can render switchers for either.
            'currentTeam' => fn () => $user?->currentTeam
                ? $user->toUserTeam($user->currentTeam)
                : null,
            'teams' => fn () => $user?->toUserTeams(includeCurrent: true) ?? [],

            // RBAC effective-permission map for the active org, keyed
            // by permission name. Lets the frontend gate UI affordances
            // (buttons, nav items) without round-tripping. Lazy so
            // unauthenticated/non-org pages don't pay the lookup cost.
            'auth.can' => fn () => $this->permissionsMap($user),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function permissionsMap(?\App\Models\User $user): array
    {
        if (! $user || ! $user->currentOrganization) {
            return [];
        }

        // The middleware has already pointed Spatie at the current org.
        // `getAllPermissions()` returns the *effective* set (role +
        // individual grants), which is what the UI needs.
        return $user->getAllPermissions()
            ->pluck('name')
            ->mapWithKeys(fn (string $name) => [$name => true])
            ->all();
    }
}
