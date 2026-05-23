<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Marketing surface: cross-event campaigns, social sharing, paid ads,
 * and the per-integration connect/disconnect screens that feed them.
 *
 * Each method here is a leaf page rather than a CRUD resource because
 * marketing concepts in v1 are deliberately thin scaffolds — campaigns
 * and sends become real once the underlying provider integrations land
 * (Mailchimp for email, the four social providers, Meta Ads for paid).
 *
 * Permissions mirror the SystemRole defaults — MarketingManager has all
 * four marketing.* permissions; SupportAgent and Member see nothing.
 */
class MarketingController extends Controller
{
    public function index(Request $request, string $current_organization): Response
    {
        $this->assertAnyMarketingPerm($request);

        return Inertia::render('marketing/index', [
            'permissions' => $this->permissionsPayload($request),
        ]);
    }

    public function emailCampaigns(Request $request, string $current_organization): Response
    {
        abort_unless($request->user()->can('marketing.email.manage'), 403);

        return Inertia::render('marketing/email-campaigns', [
            // No campaigns table yet — empty list. Page renders the
            // composer CTA + empty state until provider integration
            // (Mailchimp) is connected.
            'campaigns' => [],
            'audiences' => [
                ['key' => 'all', 'label' => 'All subscribers', 'count' => 0],
                ['key' => 'past-attendees', 'label' => 'Past attendees', 'count' => 0],
                ['key' => 'opted-in', 'label' => 'Marketing-opted-in attendees', 'count' => 0],
            ],
            'mailchimpConnected' => false,
        ]);
    }

    public function social(Request $request, string $current_organization): Response
    {
        abort_unless(
            $request->user()->can('marketing.social.publish')
            || $request->user()->can('marketing.facebook-event.manage'),
            403,
        );

        return Inertia::render('marketing/social', [
            'integrations' => [
                ['key' => 'tiktok', 'label' => 'TikTok', 'connected' => false],
                ['key' => 'linkedin', 'label' => 'LinkedIn', 'connected' => false],
                ['key' => 'instagram', 'label' => 'Instagram', 'connected' => false],
                ['key' => 'facebook', 'label' => 'Facebook', 'connected' => false],
            ],
            'permissions' => [
                'publish' => $request->user()->can('marketing.social.publish'),
                'facebookEvent' => $request->user()->can('marketing.facebook-event.manage'),
            ],
        ]);
    }

    public function paidAds(Request $request, string $current_organization): Response
    {
        abort_unless($request->user()->can('marketing.paid-ads.manage'), 403);

        return Inertia::render('marketing/paid-ads', [
            'platforms' => [
                ['key' => 'meta', 'label' => 'Facebook &amp; Instagram Ads', 'connected' => false],
                ['key' => 'tiktok', 'label' => 'TikTok Ads', 'connected' => false],
                ['key' => 'google', 'label' => 'Google Ads', 'connected' => false],
            ],
            // No campaigns persisted yet — empty list for the table.
            'campaigns' => [],
        ]);
    }

    public function integrations(Request $request, string $current_organization): Response
    {
        $this->assertAnyMarketingPerm($request);

        return Inertia::render('marketing/integrations', [
            'integrations' => [
                [
                    'key' => 'tiktok',
                    'label' => 'TikTok',
                    'category' => 'Social',
                    'connected' => false,
                    'description' => 'Share your events on TikTok when you connect your account.',
                ],
                [
                    'key' => 'instagram',
                    'label' => 'Instagram',
                    'category' => 'Social',
                    'connected' => false,
                    'description' => 'Share your events on Instagram when you connect your account.',
                ],
                [
                    'key' => 'linkedin',
                    'label' => 'LinkedIn',
                    'category' => 'Social',
                    'connected' => false,
                    'description' => 'Share your events on LinkedIn when you connect your account.',
                ],
                [
                    'key' => 'facebook',
                    'label' => 'Facebook',
                    'category' => 'Social + Ads',
                    'connected' => false,
                    'description' => 'Connect your Facebook account to set up Paid Social Ad campaigns, share events to your page, or enable the Conversions API integration.',
                ],
                [
                    'key' => 'mailchimp',
                    'label' => 'Mailchimp',
                    'category' => 'Email',
                    'connected' => false,
                    'description' => 'Continuously sync attendee emails to your Mailchimp account. Only attendees who opted into email marketing at checkout are synced.',
                ],
            ],
        ]);
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
