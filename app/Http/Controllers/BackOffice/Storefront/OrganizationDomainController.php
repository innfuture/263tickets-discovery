<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationDomain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Custom-domain self-service. Flow:
 *
 *   1. POST /domains       create a row with the requested hostname.
 *      Response includes the TXT record the operator needs to add to
 *      their DNS: `example-app-verify=<verification_token>`.
 *   2. POST /domains/{id}/verify  we resolve TXT records for the host;
 *      if a match is found, flip `verified=true`.
 *   3. Once verified, the ResolveOrganizationFromHost middleware
 *      starts routing public storefront traffic on that hostname to
 *      this org.
 */
class OrganizationDomainController extends Controller
{
    public function index(Organization $currentOrganization): JsonResponse
    {
        return response()->json([
            'data' => OrganizationDomain::query()
                ->where('organization_id', $currentOrganization->id)
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function store(Organization $currentOrganization, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hostname' => ['required', 'string', 'max:253', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $domain = OrganizationDomain::create([
            'organization_id' => $currentOrganization->id,
            'hostname' => strtolower($validated['hostname']),
            'is_primary' => (bool) ($validated['is_primary'] ?? false),
        ]);

        return response()->json([
            'data' => [
                'id' => $domain->id,
                'hostname' => $domain->hostname,
                'verification_record' => [
                    'type' => 'TXT',
                    'name' => '_example-app-verify.'.$domain->hostname,
                    'value' => 'example-app-verify='.$domain->verification_token,
                ],
                'verified' => false,
            ],
        ], 201);
    }

    public function verify(Organization $currentOrganization, OrganizationDomain $domain): JsonResponse
    {
        abort_if($domain->organization_id !== $currentOrganization->id, 404);

        $records = @dns_get_record('_example-app-verify.'.$domain->hostname, DNS_TXT);
        $expected = 'example-app-verify='.$domain->verification_token;
        $matches = collect($records ?: [])->contains(fn ($r) => ($r['txt'] ?? null) === $expected);

        if (! $matches) {
            return response()->json([
                'error' => 'txt_record_not_found',
                'expected' => $expected,
                'host' => '_example-app-verify.'.$domain->hostname,
            ], 422);
        }

        $domain->forceFill([
            'verified' => true,
            'verified_at' => now(),
        ])->save();

        return response()->json(['data' => ['verified' => true]]);
    }

    public function destroy(Organization $currentOrganization, OrganizationDomain $domain): JsonResponse
    {
        abort_if($domain->organization_id !== $currentOrganization->id, 404);
        $domain->delete();

        return response()->json(['data' => ['ok' => true]]);
    }
}
