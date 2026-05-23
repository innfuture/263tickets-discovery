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
 * Integration catalogue + connected-apps list. Each provider has a
 * declared identity here; the actual OAuth handshake would live in
 * a provider-specific controller and call back into `connect()` with
 * the granted scopes.
 */
class IntegrationController extends SettingsController
{
    public const CATALOGUE = [
        'stripe' => ['name' => 'Stripe', 'category' => 'Payments', 'description' => 'Process card payments and route payouts via Stripe Connect.', 'scopes' => ['read_charges', 'create_charges', 'refund_charges']],
        'mailchimp' => ['name' => 'Mailchimp', 'category' => 'Marketing', 'description' => 'Sync attendees to Mailchimp audiences for marketing emails.', 'scopes' => ['lists.write', 'campaigns.send']],
        'zapier' => ['name' => 'Zapier', 'category' => 'Automation', 'description' => 'Trigger Zaps on platform events; pull data via Zapier polling.', 'scopes' => ['read', 'webhook.subscribe']],
        'slack' => ['name' => 'Slack', 'category' => 'Notifications', 'description' => 'Pipe sales alerts and digests into a Slack channel.', 'scopes' => ['chat.write', 'channels.read']],
        'zoom' => ['name' => 'Zoom', 'category' => 'Video', 'description' => 'Auto-create meeting rooms for virtual events.', 'scopes' => ['meeting:write', 'webinar:write']],
        'google_calendar' => ['name' => 'Google Calendar', 'category' => 'Productivity', 'description' => 'Add events to organizer + attendee calendars.', 'scopes' => ['calendar.events']],
        'ga4' => ['name' => 'Google Analytics 4', 'category' => 'Analytics', 'description' => 'Forward attendee + sale events to your GA4 property.', 'scopes' => ['ga.measure']],
        'meta_pixel' => ['name' => 'Meta Pixel', 'category' => 'Analytics', 'description' => 'Track checkout + purchase via the Meta pixel.', 'scopes' => ['pixel.events']],
        'hubspot' => ['name' => 'HubSpot', 'category' => 'CRM', 'description' => 'Sync attendees as HubSpot contacts; create deals on high-value purchases.', 'scopes' => ['contacts.write', 'deals.write']],
        'salesforce' => ['name' => 'Salesforce', 'category' => 'CRM', 'description' => 'Mirror attendees + sales into Salesforce objects.', 'scopes' => ['lead.write', 'opportunity.write']],
    ];

    public function index(Request $request): Response
    {
        $org = $this->org($request, 'integration.manage');

        $connected = IntegrationConnection::query()
            ->where('organization_id', $org->id)
            ->whereNull('disconnected_at')
            ->get()
            ->keyBy('provider');

        $catalogue = collect(self::CATALOGUE)
            ->map(function ($meta, $key) use ($connected) {
                return [
                    'key' => $key,
                    ...$meta,
                    'is_connected' => $connected->has($key),
                ];
            })
            ->values();

        return Inertia::render('settings/integrations/index', [
            'catalogue' => $catalogue,
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Integrations', 'href' => '/settings/integrations'],
            ],
        ]);
    }

    public function connect(Request $request, string $provider, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'integration.manage');
        abort_unless(isset(self::CATALOGUE[$provider]), 404);

        $data = $request->validate([
            'account_label' => ['required', 'string', 'max:120'],
        ]);

        IntegrationConnection::updateOrCreate(
            ['organization_id' => $org->id, 'provider' => $provider],
            [
                'connected_by_id' => $request->user()->id,
                'account_label' => $data['account_label'],
                'scopes' => self::CATALOGUE[$provider]['scopes'],
                'config' => [],
                'connected_at' => now(),
                'disconnected_at' => null,
            ],
        );

        $audit->record('integration.connected', $org, $request->user(), 'integration', $provider, after: ['provider' => $provider]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':p connected.', ['p' => self::CATALOGUE[$provider]['name']])]);

        return back();
    }
}
