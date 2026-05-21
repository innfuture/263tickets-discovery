<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Routing\Route as RouteElement;
use Illuminate\Support\Facades\Route;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Rejects organization names that collide with reserved URL prefixes
 * (the org slug becomes a top-level URL segment, so it can't shadow
 * `settings`, `dashboard`, etc.) or with HTTP-status-code paths.
 *
 * Cloned from the prior TeamName rule with the same reserved list —
 * the constraints are URL-shape, not entity-shape, so they apply to
 * either tier.
 */
class OrganizationName implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $name = strtolower(trim($value));

        if (in_array($name, $this->reservedNames(), true)) {
            $fail(__('This organization name is reserved and cannot be used.'));
        }
    }

    /**
     * @return array<int, string>
     */
    protected function reservedNames(): array
    {
        return once(fn () => collect($this->routesPrefixes())
            ->merge([
                '300', '302', '400', '401', '402', '403', '404', '405', '406', '407',
                '408', '409', '410', '411', '412', '413', '414', '415', '416', '417',
                '418', '419', '420', '421', '422', '423', '424', '425', '426', '427',
                '428', '429', '430', '431', '500', '501', '502', '503', '504', '505',
                '506', '507', '508', '509', '510', '511',
                'about', 'account', 'accounts', 'admin', 'api', 'app', 'apps', 'auth',
                'billing', 'blog', 'business', 'cache', 'careers', 'changelog', 'chat',
                'cloud', 'companies', 'compare', 'contact', 'dashboard', 'design',
                'developer', 'discover', 'download', 'downloads', 'edit', 'editor',
                'enterprise', 'events', 'explore', 'features', 'feed', 'files', 'help',
                'home', 'hosting', 'images', 'inbox', 'index', 'info', 'integration',
                'invite', 'invites', 'invitations', 'jobs', 'join', 'launch', 'learn',
                'legal', 'library', 'login', 'logout', 'mail', 'mailbox', 'maintenance',
                'manage', 'marketing', 'marketplace', 'mention', 'mobile', 'new', 'news',
                'notifications', 'oauth', 'org', 'organization', 'organizations', 'orgs',
                'pages', 'partners', 'pay', 'payments', 'plans', 'plugins', 'popular',
                'posts', 'press', 'preview', 'pricing', 'privacy', 'profile', 'projects',
                'public', 'register', 'releases', 'reports', 'reset', 'resources',
                'search', 'security', 'services', 'settings', 'shop', 'signin', 'signup',
                'site', 'sitemap', 'staff', 'starred', 'static', 'status', 'storage',
                'store', 'subscriptions', 'support', 'tags', 'team', 'teams', 'terms',
                'tools', 'tos', 'tour', 'updates', 'upgrade', 'users', 'wiki', 'www',
                'o',
            ])
            ->unique()
            ->sort()
            ->values()
            ->toArray());
    }

    /**
     * @return array<int, string>
     */
    protected function routesPrefixes(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->map(fn (RouteElement $route) => $route->uri)
            ->map(fn (string $uri) => explode('/', $uri)[0])
            ->reject(fn (string $uri) => str_contains($uri, '{'))
            ->filter(fn (string $uri) => $uri !== '')
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }
}
