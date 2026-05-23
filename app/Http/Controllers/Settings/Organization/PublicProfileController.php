<?php

namespace App\Http\Controllers\Settings\Organization;

use App\Http\Controllers\Settings\SettingsController;
use App\Models\OrganizationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controls the public /o/{slug} profile — visibility, follower-prompt,
 * past-event hiding, and an embed snippet attendees can paste into
 * their own sites.
 */
class PublicProfileController extends SettingsController
{
    private const DEFAULTS = [
        'public_visible' => true,
        'hide_past_events' => false,
        'follower_prompt' => 'Be the first to hear about our upcoming shows.',
        'featured_event_ids' => [],
    ];

    public function edit(Request $request): Response
    {
        $org = $this->org($request, 'organization.update');
        $settings = OrganizationSetting::for($org);
        $profile = array_replace(self::DEFAULTS, (array) $settings->get('public', []));

        $events = $org->events()
            ->orderByDesc('starts_at')
            ->limit(50)
            ->get(['id', 'name', 'slug', 'starts_at'])
            ->map(fn ($e) => [
                'uuid' => (string) $e->id,
                'name' => $e->name,
                'starts_at' => $e->starts_at?->toIso8601String(),
            ]);

        $embedSnippet = sprintf(
            '<iframe src="%s/o/%s/embed" loading="lazy" style="width:100%%;height:480px;border:0"></iframe>',
            rtrim((string) config('app.url'), '/'),
            $org->slug,
        );

        return Inertia::render('settings/organization/public', [
            'profile' => $profile,
            'events' => $events,
            'embedSnippet' => $embedSnippet,
            'publicUrl' => sprintf('%s/o/%s', rtrim((string) config('app.url'), '/'), $org->slug),
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Public page', 'href' => '/settings/organization/public'],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $org = $this->org($request, 'organization.update');

        $data = $request->validate([
            'public_visible' => ['required', 'boolean'],
            'hide_past_events' => ['required', 'boolean'],
            'follower_prompt' => ['nullable', 'string', 'max:240'],
            'featured_event_ids' => ['nullable', 'array', 'max:6'],
            'featured_event_ids.*' => ['string'],
        ]);

        OrganizationSetting::for($org)->merge('public', $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Public profile saved.')]);

        return back();
    }
}
