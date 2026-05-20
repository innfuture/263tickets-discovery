<?php

namespace App\Http\Controllers;

use App\Enums\AdPlatform;
use App\Models\AdCampaign;
use App\Models\Event;
use App\Models\Team;
use App\Services\AdManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdCampaignController extends Controller
{
    public function __construct(
        private readonly AdManagementService $adService,
    ) {}

    public function index(string $current_team, Event $event): JsonResponse
    {
        $this->authoriseEvent($current_team, $event);

        $campaigns = $event->adCampaigns()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (AdCampaign $c) => $this->campaignPayload($c));

        return response()->json([
            'campaigns' => $campaigns,
            'platforms' => collect(AdPlatform::cases())->map(fn (AdPlatform $p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ]),
        ]);
    }

    public function store(Request $request, string $current_team, Event $event): RedirectResponse
    {
        $this->authoriseEvent($current_team, $event);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'platform' => ['required', 'string', 'in:google_ads,meta_ads,youtube'],
            'budget_daily' => ['nullable', 'numeric', 'min:1'],
            'budget_total' => ['nullable', 'numeric', 'min:1'],
            'budget_currency' => ['required', 'string', 'size:3'],
            'runs_from' => ['nullable', 'date'],
            'runs_until' => ['nullable', 'date', 'after:runs_from'],
            'targeting' => ['nullable', 'array'],
            'creative_assets' => ['nullable', 'array'],
            'platform_config' => ['nullable', 'array'],
            'external_ad_account_id' => ['nullable', 'string', 'max:255'],
        ]);

        $data['created_by_user_id'] = $request->user()->id;

        $campaign = $this->adService->create($event, $data);

        return back()->with('toast', [
            'type' => $campaign->campaign_status->value === 'failed' ? 'error' : 'success',
            'message' => $campaign->campaign_status->value === 'failed'
                ? 'Campaign created in draft — API connection required to activate.'
                : 'Campaign launched successfully.',
        ]);
    }

    public function pause(string $current_team, Event $event, AdCampaign $campaign): RedirectResponse
    {
        $this->authoriseEvent($current_team, $event);
        abort_unless($campaign->event_id === $event->id, 404);

        $this->adService->pause($campaign);

        return back()->with('toast', ['type' => 'success', 'message' => 'Campaign paused.']);
    }

    public function syncMetrics(string $current_team, Event $event, AdCampaign $campaign): JsonResponse
    {
        $this->authoriseEvent($current_team, $event);
        abort_unless($campaign->event_id === $event->id, 404);

        $campaign = $this->adService->syncMetrics($campaign);

        return response()->json($this->campaignPayload($campaign));
    }

    public function destroy(string $current_team, Event $event, AdCampaign $campaign): RedirectResponse
    {
        $this->authoriseEvent($current_team, $event);
        abort_unless($campaign->event_id === $event->id, 404);

        $campaign->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Campaign removed.']);
    }

    private function authoriseEvent(string $teamSlug, Event $event): void
    {
        $team = Team::where('slug', $teamSlug)->firstOrFail();
        abort_unless($event->organisation_id === $team->uuid, 403);
    }

    /** @return array<string, mixed> */
    private function campaignPayload(AdCampaign $c): array
    {
        return [
            'id' => $c->id,
            'uuid' => $c->uuid,
            'name' => $c->name,
            'platform' => ['value' => $c->platform->value, 'label' => $c->platform->label()],
            'campaign_status' => ['value' => $c->campaign_status->value, 'label' => $c->campaign_status->label()],
            'budget_daily' => $c->budget_daily ? (float) $c->budget_daily : null,
            'budget_total' => $c->budget_total ? (float) $c->budget_total : null,
            'budget_currency' => $c->budget_currency,
            'runs_from' => $c->runs_from?->toISOString(),
            'runs_until' => $c->runs_until?->toISOString(),
            'targeting' => $c->targeting,
            'creative_assets' => $c->creative_assets,
            'platform_config' => $c->platform_config,
            'external_campaign_id' => $c->external_campaign_id,
            'metrics' => $c->metrics,
            'metrics_synced_at' => $c->metrics_synced_at?->toISOString(),
            'created_at' => $c->created_at->toISOString(),
        ];
    }
}
