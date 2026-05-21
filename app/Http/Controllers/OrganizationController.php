<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\OrganizerType;
use App\Http\Requests\Organizations\UpdateOrganizationProfileRequest;
use App\Models\Organization;
use App\Services\ImageProcessingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Top-of-hierarchy controller. Three surfaces:
 *
 *   1. show()   — public organizer profile at `/o/{organization:slug}`.
 *                 Lists the org's upcoming + past published events,
 *                 exposes contact + social links. Eventbrite-style.
 *
 *   2. edit()   — authenticated organizer editing their own profile
 *                 from inside `/settings/organization`. Uses the
 *                 viewer's currentOrganization rather than a URL slug
 *                 since the settings sidebar is org-implicit.
 *
 *   3. update() — validate-and-persist the form, including image
 *                 processing for logo + banner via the shared
 *                 ImageProcessingService.
 */
class OrganizationController extends Controller
{
    public function show(Organization $organization, Request $request): Response
    {
        $events = $organization->events()
            ->where('status', EventStatus::Published->value)
            ->orderBy('starts_at')
            ->limit(40)
            ->get();

        $now = now();
        [$upcoming, $past] = $events->partition(
            fn ($e) => $e->starts_at === null || $e->starts_at->greaterThanOrEqualTo($now),
        );

        return Inertia::render('organization/show', [
            'organization' => $this->profilePayload($organization),
            'upcomingEvents' => $upcoming->values()->map(fn ($e) => $this->eventCardPayload($e)),
            'pastEvents' => $past->values()->map(fn ($e) => $this->eventCardPayload($e)),
            'viewerCanEdit' => $request->user()?->belongsToOrganization($organization) ?? false,
        ]);
    }

    public function edit(Request $request): Response
    {
        $organization = $this->currentOrThrow($request);

        Gate::authorize('view', $organization);

        return Inertia::render('settings/organization', [
            'organization' => $this->profilePayload($organization, includeBusinessFields: true),
            'organizerTypes' => $this->organizerTypeOptions(),
        ]);
    }

    public function update(
        UpdateOrganizationProfileRequest $request,
        ImageProcessingService $imageService,
    ): RedirectResponse {
        $organization = $this->currentOrThrow($request);

        Gate::authorize('update', $organization);

        $data = $request->safe()->except(['logo', 'banner', 'remove_logo', 'remove_banner']);

        // Replace-or-remove logic per asset. The frontend's in-image
        // overlay sends either a new file (replace) or a remove_* flag
        // (delete with no replacement). If neither is set the existing
        // path is left untouched.
        if ($request->hasFile('logo')) {
            if ($organization->logo_path) {
                $imageService->delete($organization->logo_path);
            }
            $logoFile = $request->file('logo');
            $isSvg = strtolower((string) $logoFile->getClientOriginalExtension()) === 'svg';

            $data['logo_path'] = $isSvg
                ? $logoFile->store('organizations/logos', 'public')
                : $imageService->processAndStore(
                    $logoFile,
                    'organizations/logos',
                    ImageProcessingService::SQUARE_SIZES,
                );
        } elseif ($request->boolean('remove_logo') && $organization->logo_path) {
            $imageService->delete($organization->logo_path);
            $data['logo_path'] = null;
        }

        if ($request->hasFile('banner')) {
            if ($organization->banner_path) {
                $imageService->delete($organization->banner_path);
            }
            $data['banner_path'] = $imageService->processAndStore(
                $request->file('banner'),
                'organizations/banners',
                ImageProcessingService::BANNER_SIZES,
            );
        } elseif ($request->boolean('remove_banner') && $organization->banner_path) {
            $imageService->delete($organization->banner_path);
            $data['banner_path'] = null;
        }

        $organization->update($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Organization profile updated.',
        ]);

        return back();
    }

    private function currentOrThrow(Request $request): Organization
    {
        $org = $request->user()?->currentOrganization;
        abort_if($org === null, 404);

        return $org;
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(Organization $org, bool $includeBusinessFields = false): array
    {
        $base = [
            'id' => $org->id,
            'uuid' => $org->uuid,
            'name' => $org->name,
            'brand_name' => $org->brand_name,
            'slug' => $org->slug,
            'tagline' => $org->tagline,
            'description' => $org->description,
            'organizer_type' => $org->organizer_type
                ? [
                    'value' => $org->organizer_type->value,
                    'label' => $org->organizer_type->label(),
                ]
                : null,
            'logo_url' => $org->logoUrl(),
            'banner_url' => $org->bannerUrl(),
            'contact_email' => $org->contact_email,
            'support_email' => $org->support_email,
            'contact_phone' => $org->contact_phone,
            'website_url' => $org->website_url,
            'social_links' => $org->socialLinks(),
            'address' => [
                'line_1' => $org->address_line_1,
                'line_2' => $org->address_line_2,
                'city' => $org->city,
                'region' => $org->region,
                'country_code' => $org->country_code,
                'postal_code' => $org->postal_code,
                'latitude' => $org->latitude !== null ? (float) $org->latitude : null,
                'longitude' => $org->longitude !== null ? (float) $org->longitude : null,
            ],
            'founded_year' => $org->founded_year,
            'is_verified' => (bool) $org->is_verified,
            'followers_count' => (int) ($org->followers_count ?? 0),
        ];

        if ($includeBusinessFields) {
            $base['tax_id'] = $org->tax_id;
            $base['default_currency'] = $org->default_currency;
            $base['default_timezone'] = $org->default_timezone;
        }

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    private function eventCardPayload(\App\Models\Event $event): array
    {
        return [
            'id' => $event->id,
            'slug' => $event->slug,
            'name' => $event->name,
            'short_description' => $event->short_description,
            'starts_at' => $event->starts_at?->toISOString(),
            'city' => $event->city,
            'country_code' => $event->country_code,
            'is_online' => (bool) $event->is_online,
            'banner_image_url' => $event->banner_image_path
                ? \Illuminate\Support\Facades\Storage::url($event->banner_image_path)
                : null,
        ];
    }

    /**
     * @return array<int, array{group: string, options: array<int, array{value: string, label: string}>}>
     */
    private function organizerTypeOptions(): array
    {
        return collect(OrganizerType::grouped())
            ->map(fn ($cases, $group) => [
                'group' => $group,
                'options' => collect($cases)
                    ->map(fn (OrganizerType $t) => [
                        'value' => $t->value,
                        'label' => $t->label(),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
