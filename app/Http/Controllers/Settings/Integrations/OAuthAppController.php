<?php

namespace App\Http\Controllers\Settings\Integrations;

use App\Http\Controllers\Settings\SettingsController;
use App\Models\OAuthApp;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Org-owned OAuth applications — third-party developer apps that
 * other users can authorise into this org's data. The client secret
 * is shown exactly once on create + rotate; only the hash persists.
 */
class OAuthAppController extends SettingsController
{
    public const SCOPES = ['read', 'write', 'events.write', 'tickets.write', 'finance.read', 'admin'];

    public function index(Request $request): Response
    {
        $org = $this->org($request, 'oauth_app.manage');

        $apps = OAuthApp::query()
            ->where('organization_id', $org->id)
            ->whereNull('revoked_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (OAuthApp $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'client_id' => $a->client_id,
                'redirect_uris' => $a->redirect_uris,
                'scopes' => $a->scopes,
                'homepage_url' => $a->homepage_url,
                'description' => $a->description,
                'created_at' => $a->created_at->toIso8601String(),
            ]);

        return Inertia::render('settings/integrations/oauth', [
            'apps' => $apps,
            'scopeOptions' => self::SCOPES,
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'OAuth apps', 'href' => '/settings/integrations/oauth'],
            ],
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'oauth_app.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'redirect_uris' => ['required', 'array', 'min:1', 'max:5'],
            'redirect_uris.*' => ['url', 'max:500'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['in:'.implode(',', self::SCOPES)],
            'homepage_url' => ['nullable', 'url', 'max:500'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $mint = OAuthApp::mint([
            ...$data,
            'organization_id' => $org->id,
            'created_by_id' => $request->user()->id,
        ]);

        $audit->record('oauth_app.created', $org, $request->user(), 'oauth_app', (string) $mint['model']->id, after: ['name' => $data['name']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('OAuth app created. Save the client secret — you won\'t see it again.')]);

        return back()->with('newClientSecret', $mint['secret']);
    }

    public function update(Request $request, OAuthApp $oauth, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'oauth_app.manage');
        abort_if($oauth->organization_id !== $org->id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'redirect_uris' => ['required', 'array', 'min:1', 'max:5'],
            'redirect_uris.*' => ['url', 'max:500'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['in:'.implode(',', self::SCOPES)],
            'homepage_url' => ['nullable', 'url', 'max:500'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $oauth->update($data);

        $audit->record('oauth_app.updated', $org, $request->user(), 'oauth_app', (string) $oauth->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('OAuth app saved.')]);

        return back();
    }

    public function rotateSecret(Request $request, OAuthApp $oauth, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'oauth_app.manage');
        abort_if($oauth->organization_id !== $org->id, 403);

        $secret = 'oauth_secret_'.Str::random(40);
        $oauth->update(['client_secret_hash' => hash('sha256', $secret)]);

        $audit->record('oauth_app.secret_rotated', $org, $request->user(), 'oauth_app', (string) $oauth->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client secret rotated. Save the new value.')]);

        return back()->with('newClientSecret', $secret);
    }

    public function destroy(Request $request, OAuthApp $oauth, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'oauth_app.manage');
        abort_if($oauth->organization_id !== $org->id, 403);

        $oauth->update(['revoked_at' => now()]);

        $audit->record('oauth_app.revoked', $org, $request->user(), 'oauth_app', (string) $oauth->id, before: ['name' => $oauth->name]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('OAuth app revoked.')]);

        return back();
    }
}
