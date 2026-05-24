<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Marketing surface: cross-event campaigns, social sharing, paid ads,
 * and integration management. Reads from the real platform tables
 * (`email_campaigns`, `social_posts`, `integration_connections`,
 * `ad_campaigns`) when present; falls back to safe empty arrays so
 * the page works against a fresh database too.
 *
 * Provider hand-off (OAuth + actual send/post/etc.) still lives behind
 * the integration_connections table. Connect / disconnect endpoints
 * here flip that table; deeper provider plumbing remains a per-vendor
 * project.
 */
class MarketingController extends Controller
{
    /**
     * Static catalogue of integrations the platform recognises. Merged
     * with live `integration_connections` rows in `integrations()` and
     * `social()` so the UI sees both "connected" and "available".
     *
     * @return array<int, array{key: string, label: string, category: string, description: string}>
     */
    protected function knownIntegrations(): array
    {
        return [
            ['key' => 'tiktok', 'label' => 'TikTok', 'category' => 'Social', 'description' => 'Share your events on TikTok when you connect your account.'],
            ['key' => 'instagram', 'label' => 'Instagram', 'category' => 'Social', 'description' => 'Share your events on Instagram when you connect your account.'],
            ['key' => 'linkedin', 'label' => 'LinkedIn', 'category' => 'Social', 'description' => 'Share your events on LinkedIn when you connect your account.'],
            ['key' => 'facebook', 'label' => 'Facebook', 'category' => 'Social + Ads', 'description' => 'Connect your Facebook account to set up Paid Social Ad campaigns, share events to your page, or enable the Conversions API integration.'],
            ['key' => 'mailchimp', 'label' => 'Mailchimp', 'category' => 'Email', 'description' => 'Continuously sync attendee emails to your Mailchimp account. Only attendees who opted into email marketing at checkout are synced.'],
            ['key' => 'google', 'label' => 'Google Ads', 'category' => 'Ads', 'description' => 'Run Google Search / Display campaigns for your events.'],
            ['key' => 'meta', 'label' => 'Meta Ads', 'category' => 'Ads', 'description' => 'Run Facebook + Instagram paid campaigns and sync the Conversions API.'],
        ];
    }

    public function index(Request $request, string $current_organization): Response
    {
        $this->assertAnyMarketingPerm($request);

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        return Inertia::render('marketing/index', [
            'permissions' => $this->permissionsPayload($request),
            'stats' => [
                'email_campaigns' => $this->countOrgRows('email_campaigns', $org->id),
                'social_posts' => $this->countOrgRows('social_posts', $org->id),
                'ad_campaigns' => (int) AdCampaign::query()->where('organisation_id', $org->uuid)->count(),
                'connected_integrations' => $this->countOrgRows('integration_connections', $org->id),
            ],
            'breadcrumbs' => [
                ['title' => 'Marketing', 'href' => "/{$current_organization}/marketing"],
            ],
        ]);
    }

    public function emailCampaigns(Request $request, string $current_organization): Response
    {
        abort_unless($request->user()->can('marketing.email.manage'), 403);

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        $campaigns = Schema::hasTable('email_campaigns')
            ? DB::table('email_campaigns')
                ->where('organization_id', $org->id)
                ->orderByDesc('id')
                ->limit(100)
                ->get()
                ->map(fn ($r) => [
                    'id' => (int) $r->id,
                    'name' => (string) $r->name,
                    'subject' => (string) $r->subject,
                    'audience' => (string) ($r->audience_key ?? 'all'),
                    'status' => (string) ($r->status ?? 'draft'),
                    'recipient_count' => (int) ($r->recipient_count ?? 0),
                    'opened_count' => (int) ($r->opened_count ?? 0),
                    'clicked_count' => (int) ($r->clicked_count ?? 0),
                    'scheduled_for' => $r->scheduled_for ?? null,
                    'sent_at' => $r->sent_at ?? null,
                ])
                ->all()
            : [];

        // Audience counts — derive from real order/attendee data so the
        // numbers are alive even before Mailchimp connects.
        $audiences = [
            ['key' => 'all', 'label' => 'All buyers', 'count' => $this->distinctBuyerCount($org->uuid)],
            ['key' => 'past-attendees', 'label' => 'Past attendees (checked-in)', 'count' => $this->checkedInBuyerCount($org->uuid)],
            ['key' => 'opted-in', 'label' => 'Marketing-opted-in', 'count' => $this->distinctBuyerCount($org->uuid)],
        ];

        return Inertia::render('marketing/email-campaigns', [
            'campaigns' => $campaigns,
            'audiences' => $audiences,
            'mailchimpConnected' => $this->isProviderConnected($org->id, 'mailchimp'),
            'permissions' => [
                'can_send' => $request->user()->can('marketing.email.manage'),
            ],
            'breadcrumbs' => [
                ['title' => 'Marketing', 'href' => "/{$current_organization}/marketing"],
                ['title' => 'Email campaigns', 'href' => "/{$current_organization}/marketing/email-campaigns"],
            ],
        ]);
    }

    public function social(Request $request, string $current_organization): Response
    {
        abort_unless(
            $request->user()->can('marketing.social.publish')
            || $request->user()->can('marketing.facebook-event.manage'),
            403,
        );

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        $integrations = collect(['tiktok', 'linkedin', 'instagram', 'facebook'])
            ->map(fn (string $k) => [
                'key' => $k,
                'label' => collect($this->knownIntegrations())->firstWhere('key', $k)['label'] ?? ucfirst($k),
                'connected' => $this->isProviderConnected($org->id, $k),
            ])
            ->all();

        $posts = Schema::hasTable('social_posts')
            ? DB::table('social_posts')
                ->where('organization_id', $org->id)
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(fn ($r) => [
                    'id' => (int) $r->id,
                    'event_id' => $r->event_id ? (int) $r->event_id : null,
                    'body' => (string) $r->body,
                    'channels' => is_string($r->channels) ? json_decode($r->channels, true) : (array) $r->channels,
                    'status' => (string) ($r->status ?? 'draft'),
                    'scheduled_for' => $r->scheduled_for ?? null,
                    'published_at' => $r->published_at ?? null,
                ])
                ->all()
            : [];

        return Inertia::render('marketing/social', [
            'integrations' => $integrations,
            'posts' => $posts,
            'event_options' => $this->eventOptions($org->id),
            'permissions' => [
                'publish' => $request->user()->can('marketing.social.publish'),
                'facebookEvent' => $request->user()->can('marketing.facebook-event.manage'),
            ],
            'breadcrumbs' => [
                ['title' => 'Marketing', 'href' => "/{$current_organization}/marketing"],
                ['title' => 'Social', 'href' => "/{$current_organization}/marketing/social"],
            ],
        ]);
    }

    public function paidAds(Request $request, string $current_organization): Response
    {
        abort_unless($request->user()->can('marketing.paid-ads.manage'), 403);

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        // Ad-platform connection state derived from integration_connections.
        $platforms = collect(['meta', 'tiktok', 'google'])
            ->map(fn (string $k) => [
                'key' => $k,
                'label' => collect($this->knownIntegrations())->firstWhere('key', $k)['label'] ?? ucfirst($k),
                'connected' => $this->isProviderConnected($org->id, $k),
            ])
            ->all();

        // Real ad campaigns from the existing ad_campaigns table —
        // org-scoped through the UUID FK convention this schema uses.
        $campaigns = AdCampaign::query()
            ->where('organisation_id', $org->uuid)
            ->with('event:id,slug,name')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (AdCampaign $c) => [
                'id' => (int) $c->id,
                'name' => $c->name,
                'platform' => $c->platform?->value,
                'status' => $c->campaign_status?->value,
                'budget_daily' => $c->budget_daily,
                'budget_total' => $c->budget_total,
                'budget_currency' => $c->budget_currency,
                'runs_from' => $c->runs_from?->toIso8601String(),
                'runs_until' => $c->runs_until?->toIso8601String(),
                'event' => $c->event ? ['slug' => $c->event->slug, 'name' => $c->event->name] : null,
                'metrics' => $c->metrics,
                'metrics_synced_at' => $c->metrics_synced_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('marketing/paid-ads', [
            'platforms' => $platforms,
            'campaigns' => $campaigns,
            'breadcrumbs' => [
                ['title' => 'Marketing', 'href' => "/{$current_organization}/marketing"],
                ['title' => 'Paid ads', 'href' => "/{$current_organization}/marketing/paid-ads"],
            ],
        ]);
    }

    public function integrations(Request $request, string $current_organization): Response
    {
        $this->assertAnyMarketingPerm($request);

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        $connected = Schema::hasTable('integration_connections')
            ? DB::table('integration_connections')
                ->where('organization_id', $org->id)
                ->get()
                ->keyBy('provider')
            : collect();

        $integrations = collect($this->knownIntegrations())
            ->map(function (array $row) use ($connected) {
                $live = $connected->get($row['key']);

                return $row + [
                    'connected' => $live !== null,
                    'account_label' => $live->account_label ?? null,
                    'connected_at' => $live->connected_at ?? null,
                ];
            })
            ->all();

        return Inertia::render('marketing/integrations', [
            'integrations' => $integrations,
            'breadcrumbs' => [
                ['title' => 'Marketing', 'href' => "/{$current_organization}/marketing"],
                ['title' => 'Integrations', 'href' => "/{$current_organization}/marketing/integrations"],
            ],
        ]);
    }

    /* ────────────────────── mutate actions ────────────────────── */

    public function storeEmailCampaign(Request $request, string $current_organization, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('marketing.email.manage'), 403);
        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'subject' => ['required', 'string', 'max:191'],
            'body_html' => ['nullable', 'string'],
            'audience_key' => ['required', 'in:all,past-attendees,opted-in'],
        ]);

        DB::table('email_campaigns')->insert($data + [
            'organization_id' => $org->id,
            'created_by' => $request->user()->id,
            'status' => 'draft',
            'recipient_count' => match ($data['audience_key']) {
                'past-attendees' => $this->checkedInBuyerCount($org->uuid),
                default => $this->distinctBuyerCount($org->uuid),
            },
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $audit->record('marketing.email.created', $org, $request->user(), after: ['name' => $data['name']]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft email campaign saved.']);

        return back();
    }

    public function sendEmailCampaign(Request $request, string $current_organization, int $campaign, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('marketing.email.manage'), 403);
        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        $updated = DB::table('email_campaigns')
            ->where('id', $campaign)
            ->where('organization_id', $org->id)
            ->where('status', 'draft')
            ->update([
                'status' => 'sent',
                'sent_at' => now(),
                'updated_at' => now(),
            ]);

        abort_if($updated === 0, 404, 'Campaign not found or not in draft state.');

        $audit->record('marketing.email.sent', $org, $request->user(), after: ['campaign_id' => $campaign]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Campaign marked sent.']);

        return back();
    }

    public function storeSocialPost(Request $request, string $current_organization, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('marketing.social.publish'), 403);
        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['string', 'in:tiktok,linkedin,instagram,facebook,twitter'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'scheduled_for' => ['nullable', 'date'],
        ]);

        DB::table('social_posts')->insert([
            'organization_id' => $org->id,
            'created_by' => $request->user()->id,
            'event_id' => $data['event_id'] ?? null,
            'channels' => json_encode($data['channels']),
            'body' => $data['body'],
            'status' => empty($data['scheduled_for']) ? 'draft' : 'scheduled',
            'scheduled_for' => $data['scheduled_for'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $audit->record('marketing.social.created', $org, $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Post queued.']);

        return back();
    }

    public function connectIntegration(Request $request, string $current_organization, string $provider, AuditLogger $audit): RedirectResponse
    {
        $this->assertAnyMarketingPerm($request);
        abort_unless(in_array($provider, array_column($this->knownIntegrations(), 'key'), true), 404);

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        $data = $request->validate([
            'account_label' => ['nullable', 'string', 'max:191'],
        ]);

        DB::table('integration_connections')->updateOrInsert(
            ['organization_id' => $org->id, 'provider' => $provider],
            [
                'connected_by' => $request->user()->id,
                'account_label' => $data['account_label'] ?? null,
                'connected_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $audit->record('marketing.integration.connected', $org, $request->user(), after: ['provider' => $provider]);
        Inertia::flash('toast', ['type' => 'success', 'message' => ucfirst($provider).' connected.']);

        return back();
    }

    public function disconnectIntegration(Request $request, string $current_organization, string $provider, AuditLogger $audit): RedirectResponse
    {
        $this->assertAnyMarketingPerm($request);
        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        DB::table('integration_connections')
            ->where('organization_id', $org->id)
            ->where('provider', $provider)
            ->delete();

        $audit->record('marketing.integration.disconnected', $org, $request->user(), after: ['provider' => $provider]);
        Inertia::flash('toast', ['type' => 'success', 'message' => ucfirst($provider).' disconnected.']);

        return back();
    }

    /* ────────────────────── helpers ────────────────────── */

    protected function countOrgRows(string $table, int $orgId): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return (int) DB::table($table)->where('organization_id', $orgId)->count();
    }

    protected function distinctBuyerCount(string $orgUuid): int
    {
        return (int) Order::query()
            ->where('organisation_id', $orgUuid)
            ->whereNotNull('buyer_email')
            ->distinct('buyer_email')
            ->count('buyer_email');
    }

    protected function checkedInBuyerCount(string $orgUuid): int
    {
        return (int) OrderItem::query()
            ->whereNotNull('checked_in_at')
            ->whereIn('order_id', Order::where('organisation_id', $orgUuid)->select('id'))
            ->whereNotNull('attendee_email')
            ->distinct('attendee_email')
            ->count('attendee_email');
    }

    protected function isProviderConnected(int $orgId, string $provider): bool
    {
        if (! Schema::hasTable('integration_connections')) {
            return false;
        }

        return DB::table('integration_connections')
            ->where('organization_id', $orgId)
            ->where('provider', $provider)
            ->exists();
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    protected function eventOptions(int $orgId): array
    {
        return Event::query()
            ->where('organisation_id', $orgId)
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name'])
            ->map(fn (Event $e) => ['value' => (int) $e->id, 'label' => $e->name])
            ->all();
    }

    private function assertAnyMarketingPerm(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->can('marketing.email.manage')
            || $user->can('marketing.social.publish')
            || $user->can('marketing.facebook-event.manage')
            || $user->can('marketing.paid-ads.manage'),
            403,
        );
    }

    /**
     * @return array<string, bool>
     */
    private function permissionsPayload(Request $request): array
    {
        $user = $request->user();

        return [
            'email' => $user->can('marketing.email.manage'),
            'social' => $user->can('marketing.social.publish'),
            'facebookEvent' => $user->can('marketing.facebook-event.manage'),
            'paidAds' => $user->can('marketing.paid-ads.manage'),
        ];
    }
}
