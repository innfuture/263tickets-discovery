<?php

namespace App\Http\Controllers\Settings\Developer;

use App\Http\Controllers\Settings\SettingsController;
use App\Models\ApiKey;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Org-scoped server-to-server API keys. The plain key is shown once
 * on create + rotate; SHA-256 hash + prefix is what persists.
 */
class ApiKeyController extends SettingsController
{
    public const SCOPES = ['read', 'write', 'events.write', 'tickets.write', 'finance.read', 'admin'];

    public function index(Request $request): Response
    {
        $org = $this->org($request, 'api.manage-keys');

        $keys = ApiKey::query()
            ->where('organization_id', $org->id)
            ->with('creator:id,name,email')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ApiKey $k) => [
                'id' => $k->id,
                'name' => $k->name,
                'prefix' => $k->prefix,
                'scopes' => $k->scopes ?? [],
                'ip_allowlist' => $k->ip_allowlist ?? [],
                'last_used_at' => $k->last_used_at?->toIso8601String(),
                'expires_at' => $k->expires_at?->toIso8601String(),
                'revoked_at' => $k->revoked_at?->toIso8601String(),
                'created_by' => $k->creator?->name,
                'created_at' => $k->created_at->toIso8601String(),
                'is_active' => $k->isActive(),
            ]);

        return Inertia::render('settings/developer/api-keys', [
            'keys' => $keys,
            'scopeOptions' => self::SCOPES,
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'API keys', 'href' => '/settings/developer/api-keys'],
            ],
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'api.manage-keys');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['in:'.implode(',', self::SCOPES)],
            'ip_allowlist' => ['nullable', 'array', 'max:32'],
            'ip_allowlist.*' => ['ip'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $mint = ApiKey::mint([
            'organization_id' => $org->id,
            'created_by_id' => $request->user()->id,
            'name' => $data['name'],
            'scopes' => $data['scopes'],
            'ip_allowlist' => $data['ip_allowlist'] ?? null,
            'expires_at' => isset($data['expires_in_days']) ? now()->addDays($data['expires_in_days']) : null,
        ]);

        $audit->record('api_key.created', $org, $request->user(), 'api_key', (string) $mint['model']->id, after: ['name' => $data['name']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('API key created. Save it now — you won\'t see it again.')]);

        return back()->with('newApiKey', $mint['plain']);
    }

    public function rotate(Request $request, ApiKey $apiKey, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'api.manage-keys');
        abort_if($apiKey->organization_id !== $org->id, 403);

        $apiKey->update(['revoked_at' => now()]);

        $mint = ApiKey::mint([
            'organization_id' => $org->id,
            'created_by_id' => $request->user()->id,
            'name' => $apiKey->name.' (rotated)',
            'scopes' => $apiKey->scopes,
            'ip_allowlist' => $apiKey->ip_allowlist,
            'expires_at' => $apiKey->expires_at,
        ]);

        $audit->record('api_key.rotated', $org, $request->user(), 'api_key', (string) $apiKey->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Key rotated. Update your integrations with the new value.')]);

        return back()->with('newApiKey', $mint['plain']);
    }

    public function destroy(Request $request, ApiKey $apiKey, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'api.manage-keys');
        abort_if($apiKey->organization_id !== $org->id, 403);

        $apiKey->update(['revoked_at' => now()]);

        $audit->record('api_key.revoked', $org, $request->user(), 'api_key', (string) $apiKey->id, before: ['name' => $apiKey->name]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Key revoked.')]);

        return back();
    }
}
