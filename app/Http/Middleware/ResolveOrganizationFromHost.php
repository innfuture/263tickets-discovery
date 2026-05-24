<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\OrganizationDomain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * If the request's Host header matches a verified entry in
 * `organization_domains`, attach the resolved Organization to the
 * request so public controllers can scope their output.
 *
 *   $org = $request->attributes->get('host_organization');
 *
 * Falls through (no-op) for requests on the canonical app domain.
 * Doesn't authenticate, doesn't change routing — controllers opt
 * in by reading the attribute.
 */
class ResolveOrganizationFromHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower((string) $request->getHost());
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if ($host === $appHost || $host === '' || $host === 'localhost') {
            return $next($request);
        }

        $domain = OrganizationDomain::query()
            ->where('hostname', $host)
            ->where('verified', true)
            ->with('organization')
            ->first();

        if ($domain?->organization) {
            $request->attributes->set('host_organization', $domain->organization);
        }

        return $next($request);
    }
}
