<?php

namespace App\Http\Controllers\Settings\Integrations;

use App\Http\Controllers\Settings\SettingsController;
use App\Models\IntegrationConnection;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Connected-apps list — exactly the integrations that have an active
 * connection row for this org. Disconnecting flips `disconnected_at`
 * rather than deleting, so we keep a paper trail of which apps have
 * ever held scope to the org's data.
 */
class ConnectedController extends SettingsController
{
    public function index(Request $request): Response
    {
        $org = $this->org($request, 'integration.manage');

        $connections = IntegrationConnection::query()
            ->where('organization_id', $org->id)
            ->whereNull('disconnected_at')
            ->with('connector:id,name,email')
            ->orderByDesc('connected_at')
            ->get()
            ->map(function (IntegrationConnection $c) {
                $meta = IntegrationController::CATALOGUE[$c->provider] ?? ['name' => $c->provider, 'category' => 'Other'];

                return [
                    'id' => $c->id,
                    'provider' => $c->provider,
                    'provider_name' => $meta['name'],
                    'category' => $meta['category'],
                    'account_label' => $c->account_label,
                    'scopes' => $c->scopes ?? [],
                    'connected_by' => $c->connector?->name,
                    'connected_at' => $c->connected_at?->toIso8601String(),
                ];
            });

        return Inertia::render('settings/integrations/connected', [
            'connections' => $connections,
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Connected apps', 'href' => '/settings/integrations/connected'],
            ],
        ]);
    }

    public function destroy(Request $request, IntegrationConnection $connection, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'integration.manage');
        abort_if($connection->organization_id !== $org->id, 403);

        $connection->update(['disconnected_at' => now()]);

        $audit->record('integration.disconnected', $org, $request->user(), 'integration', $connection->provider, before: ['provider' => $connection->provider]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('App disconnected.')]);

        return back();
    }
}
