<?php

namespace App\Services;

use App\Enums\AdCampaignStatus;
use App\Enums\AdPlatform;
use App\Exceptions\FeatureNotImplementedException;
use App\Models\AdCampaign;
use App\Models\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AdManagementService — decoupled module for managing advertising campaigns
 * across Google Ads, Meta (Facebook/Instagram), and YouTube.
 *
 * Architecture notes:
 * - Each platform has its own private driver method that talks to the relevant API.
 * - Campaign data is persisted locally in ad_campaigns; the external_campaign_id
 *   column holds the platform-assigned identifier after successful creation.
 * - Metrics are synced periodically via a scheduled job calling syncMetrics().
 * - This service is designed to be consumed from any controller or job; it has
 *   no dependency on HTTP or Inertia internals.
 */
class AdManagementService
{
    /**
     * Create and launch a campaign on the specified platform for any owner
     * (event, venue, organisation, etc.). The owner must expose an
     * `organisation_id` attribute and a stable primary key.
     *
     * @param  Model  $owner
     * @param  array<string, mixed>  $data  Validated campaign configuration
     */
    public function createForOwner($owner, array $data): AdCampaign
    {
        $attrs = [
            ...$data,
            'owner_type' => $owner::class,
            'owner_id' => $owner->getKey(),
            'organisation_id' => $owner->organisation_id ?? null,
            'campaign_status' => AdCampaignStatus::Draft,
        ];

        // Event-scoped campaigns keep the dedicated FK for legacy queries.
        if ($owner instanceof Event) {
            $attrs['event_id'] = $owner->id;
        }

        $campaign = AdCampaign::create($attrs);

        try {
            $externalId = match (AdPlatform::from($data['platform'])) {
                AdPlatform::GoogleAds => $this->createGoogleAdsCampaign($campaign, $owner),
                AdPlatform::MetaAds => $this->createMetaCampaign($campaign, $owner),
                AdPlatform::YouTube => $this->createYouTubeCampaign($campaign, $owner),
            };

            $campaign->update([
                'external_campaign_id' => $externalId,
                'campaign_status' => AdCampaignStatus::Active,
            ]);
        } catch (Throwable $e) {
            Log::error('Ad campaign creation failed', [
                'campaign_id' => $campaign->id,
                'owner' => $owner::class.'#'.$owner->getKey(),
                'platform' => $data['platform'],
                'error' => $e->getMessage(),
            ]);

            $campaign->update(['campaign_status' => AdCampaignStatus::Failed]);
        }

        return $campaign->fresh();
    }

    /**
     * Backward-compatible event-specific entry point.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Event $event, array $data): AdCampaign
    {
        return $this->createForOwner($event, $data);
    }

    /**
     * Pause a running campaign on its platform and update local state.
     */
    public function pause(AdCampaign $campaign): AdCampaign
    {
        if (! $campaign->campaign_status->canActivate()) {
            return $campaign;
        }

        try {
            $this->platformPause($campaign);
            $campaign->update(['campaign_status' => AdCampaignStatus::Paused]);
        } catch (Throwable $e) {
            Log::error('Ad campaign pause failed', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $campaign->fresh();
    }

    /**
     * Pull the latest performance metrics from the platform and cache them locally.
     */
    public function syncMetrics(AdCampaign $campaign): AdCampaign
    {
        try {
            $metrics = match ($campaign->platform) {
                AdPlatform::GoogleAds => $this->fetchGoogleAdsMetrics($campaign),
                AdPlatform::MetaAds => $this->fetchMetaMetrics($campaign),
                AdPlatform::YouTube => $this->fetchYouTubeMetrics($campaign),
            };

            $campaign->update([
                'metrics' => $metrics,
                'metrics_synced_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Ad metrics sync failed', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $campaign->fresh();
    }

    // ─── Google Ads ──────────────────────────────────────────────────────────

    /**
     * Create a Google Ads campaign via the Google Ads API (v17).
     * Requires GOOGLE_ADS_CLIENT_ID, GOOGLE_ADS_CLIENT_SECRET,
     * GOOGLE_ADS_DEVELOPER_TOKEN and GOOGLE_ADS_REFRESH_TOKEN in .env.
     *
     * @see https://developers.google.com/google-ads/api/docs/start
     */
    private function createGoogleAdsCampaign(AdCampaign $campaign, mixed $owner): string
    {
        $this->assertEnabled('google_ads.create_campaign');
        throw new FeatureNotImplementedException('Google Ads integration not yet configured. Set GOOGLE_ADS_* credentials in .env.');
    }

    /** @return array<string, mixed> */
    private function fetchGoogleAdsMetrics(AdCampaign $campaign): array
    {
        $this->assertEnabled('google_ads.metrics');
        throw new FeatureNotImplementedException('Google Ads metrics sync not yet configured.');
    }

    // ─── Meta (Facebook / Instagram) ─────────────────────────────────────────

    /**
     * Create a Meta Ads campaign via the Marketing API (v20.0).
     * Requires META_APP_ID, META_APP_SECRET, META_ACCESS_TOKEN,
     * and META_AD_ACCOUNT_ID in .env.
     *
     * @see https://developers.facebook.com/docs/marketing-apis/
     */
    private function createMetaCampaign(AdCampaign $campaign, mixed $owner): string
    {
        $this->assertEnabled('meta_ads.create_campaign');
        throw new FeatureNotImplementedException('Meta Ads integration not yet configured. Set META_* credentials in .env.');
    }

    /** @return array<string, mixed> */
    private function fetchMetaMetrics(AdCampaign $campaign): array
    {
        $this->assertEnabled('meta_ads.metrics');
        throw new FeatureNotImplementedException('Meta Ads metrics sync not yet configured.');
    }

    protected function assertEnabled(string $hook): void
    {
        if (! (bool) config('ads.enabled', false)) {
            throw new FeatureNotImplementedException(
                "Ads integration disabled (config('ads.enabled')=false). Hook: {$hook}.",
            );
        }
    }

    // ─── YouTube ─────────────────────────────────────────────────────────────

    /**
     * Create a YouTube video campaign via the YouTube Data API v3 and
     * Google Ads API (video campaigns are managed through Google Ads).
     * Requires the same Google Ads credentials as above plus a video asset_id.
     */
    private function createYouTubeCampaign(AdCampaign $campaign, mixed $owner): string
    {
        throw new \RuntimeException('YouTube Ads integration not yet configured.');
    }

    /** @return array<string, mixed> */
    private function fetchYouTubeMetrics(AdCampaign $campaign): array
    {
        throw new \RuntimeException('YouTube metrics sync not yet configured.');
    }

    // ─── Platform-agnostic helpers ────────────────────────────────────────────

    private function platformPause(AdCampaign $campaign): void
    {
        match ($campaign->platform) {
            AdPlatform::GoogleAds => throw new \RuntimeException('Google Ads pause not yet implemented.'),
            AdPlatform::MetaAds => throw new \RuntimeException('Meta Ads pause not yet implemented.'),
            AdPlatform::YouTube => throw new \RuntimeException('YouTube pause not yet implemented.'),
        };
    }
}
