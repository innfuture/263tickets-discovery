<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Seed `current_organization` (and the legacy `team` alias used by
 * sub-team links) into URL::defaults so route() helpers don't have to
 * spell the slug at every call site.
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
        }

        return $next($request);
    }
}
