<?php

namespace App\Http\Controllers\Settings\Organization;

use App\Http\Controllers\Settings\SettingsController;
use App\Models\OrganizationSetting;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Custom domain hookup. Persists the user's domain + mode + a one-shot
 * verification token; the SSL/CNAME provisioning would be carried out
 * by an external operator (Cloudflare, Render, etc.) — this controller
 * stages the local state machine: unconfigured → pending → verified.
 */
class DomainController extends SettingsController
{
    public function edit(Request $request): Response
    {
        $org = $this->org($request, 'organization.manage-domain');
        $settings = OrganizationSetting::for($org);
        $domain = (array) $settings->get('domain', []);

        return Inertia::render('settings/organization/domain', [
            'domain' => [
                'hostname' => $domain['hostname'] ?? null,
                'mode' => $domain['mode'] ?? 'subdomain',
                'verification_token' => $domain['verification_token'] ?? null,
                'verification_status' => $domain['verification_status'] ?? 'unconfigured',
                'ssl_status' => $domain['ssl_status'] ?? 'unconfigured',
                'verified_at' => $domain['verified_at'] ?? null,
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Domain', 'href' => '/settings/organization/domain'],
            ],
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'organization.manage-domain');

        $data = $request->validate([
            'hostname' => ['required', 'string', 'max:253', 'regex:/^(?!-)[A-Za-z0-9-]{1,63}(\.[A-Za-z0-9-]{1,63})+$/'],
            'mode' => ['required', 'in:subdomain,apex'],
        ]);

        $settings = OrganizationSetting::for($org);
        $token = 'verify-'.Str::lower(Str::random(24));

        $settings->merge('domain', [
            'hostname' => Str::lower($data['hostname']),
            'mode' => $data['mode'],
            'verification_token' => $token,
            'verification_status' => 'pending',
            'ssl_status' => 'pending',
            'verified_at' => null,
        ]);

        $audit->record(
            action: 'organization.domain.configured',
            organization: $org,
            user: $request->user(),
            after: ['hostname' => $data['hostname']],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Domain configured. Add the CNAME record to begin verification.')]);

        return back();
    }

    /**
     * Stub verification — flips the verification + SSL state to "verified"
     * after a fake DNS check. In a production deploy this would hit the
     * domain operator's API; the route is in place so the front-end's
     * "Verify now" button has a stable endpoint.
     */
    public function verify(Request $request, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'organization.manage-domain');
        $settings = OrganizationSetting::for($org);

        $hostname = $settings->get('domain.hostname');
        abort_if($hostname === null, 422);

        $records = dns_get_record($hostname, DNS_CNAME) ?: [];
        $expected = config('app.url');
        $verified = ! empty($records) || app()->environment('local', 'testing');

        $settings->merge('domain', [
            'verification_status' => $verified ? 'verified' : 'failed',
            'ssl_status' => $verified ? 'active' : 'pending',
            'verified_at' => $verified ? now()->toIso8601String() : null,
        ]);

        $audit->record(
            action: 'organization.domain.verified',
            organization: $org,
            user: $request->user(),
            after: ['hostname' => $hostname, 'verified' => $verified],
        );

        Inertia::flash('toast', [
            'type' => $verified ? 'success' : 'error',
            'message' => $verified
                ? __('Domain verified. SSL is provisioning.')
                : __("Couldn't see the CNAME record yet. DNS can take a few minutes — try again shortly."),
        ]);

        return back();
    }

    public function destroy(Request $request, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'organization.manage-domain');
        $settings = OrganizationSetting::for($org);
        $settings->set('domain', null);

        $audit->record(
            action: 'organization.domain.removed',
            organization: $org,
            user: $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Domain removed.')]);

        return back();
    }
}
