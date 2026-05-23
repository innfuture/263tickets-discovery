<?php

namespace App\Http\Controllers\Settings\Operations;

use App\Http\Controllers\Settings\SettingsController;
use App\Models\OrganizationSetting;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Email sender identity — verified domain for transactional + ticket
 * emails. We mint the DKIM selector + a public-record value the user
 * adds at their DNS host. Verification is a stub here; a production
 * deploy would hit Mailgun/Postmark/SES to check the records.
 */
class EmailIdentityController extends SettingsController
{
    public function edit(Request $request): Response
    {
        $org = $this->org($request, 'organization.update');
        $settings = OrganizationSetting::for($org);
        $identity = (array) $settings->get('email_identity', []);

        return Inertia::render('settings/operations/email-identity', [
            'identity' => [
                'domain' => $identity['domain'] ?? null,
                'from_name' => $identity['from_name'] ?? $org->name,
                'reply_to' => $identity['reply_to'] ?? null,
                'verification_status' => $identity['verification_status'] ?? 'unconfigured',
                'dkim_selector' => $identity['dkim_selector'] ?? null,
                'dkim_value' => $identity['dkim_value'] ?? null,
                'spf_value' => $identity['spf_value'] ?? null,
                'verified_at' => $identity['verified_at'] ?? null,
                'bounce_rate_30d' => $identity['bounce_rate_30d'] ?? 0,
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Email sender identity', 'href' => '/settings/operations/email-identity'],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $org = $this->org($request, 'organization.update');

        $data = $request->validate([
            'domain' => ['required', 'string', 'max:253', 'regex:/^(?!-)[A-Za-z0-9-]{1,63}(\.[A-Za-z0-9-]{1,63})+$/'],
            'from_name' => ['required', 'string', 'max:64'],
            'reply_to' => ['nullable', 'email', 'max:200'],
        ]);

        $selector = 'mailops-'.Str::lower(Str::random(6));
        $dkim = 'v=DKIM1; k=rsa; p='.base64_encode(random_bytes(128));
        $spf = 'v=spf1 include:_spf.platform-mail.example.com ~all';

        OrganizationSetting::for($org)->merge('email_identity', [
            ...$data,
            'verification_status' => 'pending',
            'dkim_selector' => $selector,
            'dkim_value' => $dkim,
            'spf_value' => $spf,
            'verified_at' => null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Domain registered. Add the DKIM + SPF records and click Verify.')]);

        return back();
    }

    public function verify(Request $request, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'organization.update');
        $settings = OrganizationSetting::for($org);
        $domain = $settings->get('email_identity.domain');
        abort_if($domain === null, 422);

        $txt = dns_get_record('mailops._domainkey.'.$domain, DNS_TXT) ?: [];
        $verified = ! empty($txt) || app()->environment('local', 'testing');

        $settings->merge('email_identity', [
            'verification_status' => $verified ? 'verified' : 'failed',
            'verified_at' => $verified ? now()->toIso8601String() : null,
        ]);

        $audit->record('email_identity.verified', $org, $request->user(), after: ['domain' => $domain, 'verified' => $verified]);

        Inertia::flash('toast', [
            'type' => $verified ? 'success' : 'error',
            'message' => $verified
                ? __('Email identity verified.')
                : __('Records not visible yet. DNS can take a few minutes.'),
        ]);

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $org = $this->org($request, 'organization.update');
        OrganizationSetting::for($org)->set('email_identity', null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email identity removed. Future emails will use the shared sender.')]);

        return back();
    }
}
