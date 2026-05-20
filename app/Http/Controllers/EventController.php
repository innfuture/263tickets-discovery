<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Http\Requests\Events\SaveEventRequest;
use App\Http\Requests\Events\UpdateSeoRequest;
use App\Enums\SponsorTier;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\EventMediaItem;
use App\Models\Team;
use App\Services\ImageProcessingService;
use App\Services\WeatherForecastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    private const PER_PAGE = 24;

    public function index(Request $request, string $current_team): Response
    {
        $team = Team::where('slug', $current_team)->firstOrFail();

        $events = Event::query()
            ->where('organisation_id', $team->uuid)
            ->when(
                $request->string('name')->trim()->value(),
                fn ($q, $name) => $q->where('name', 'like', "%{$name}%"),
            )
            ->when(
                $request->string('status')->value(),
                fn ($q, $status) => $q->where('status', $status),
            )
            ->when(
                $request->string('visibility')->value(),
                fn ($q, $visibility) => $q->where('visibility', $visibility),
            )
            ->when(
                $request->boolean('featured'),
                fn ($q) => $q->where('is_featured', true),
            )
            ->when(
                $request->date('from'),
                fn ($q, $from) => $q->where('starts_at', '>=', $from),
            )
            ->when(
                $request->date('to'),
                fn ($q, $to) => $q->where('starts_at', '<=', $to->endOfDay()),
            )
            ->orderByDesc('is_featured')
            ->orderBy('starts_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Event $event) => [
                'event_id' => $event->event_id,
                'slug' => $event->slug,
                'name' => $event->name,
                'status' => $event->status->value,
                'status_label' => $event->status->label(),
                'visibility' => $event->visibility->value,
                'visibility_label' => $event->visibility->label(),
                'is_featured' => $event->is_featured,
                'starts_at' => $event->starts_at?->toISOString(),
                'ends_at' => $event->ends_at?->toISOString(),
                'timezone' => $event->timezone,
                'city' => $event->city,
                'country_code' => $event->country_code,
                'is_online' => $event->is_online,
                'banner_image_url' => $event->banner_image_path
                    ? Storage::url($event->banner_image_path)
                    : null,
                'capacity' => $event->capacity,
                'tickets_sold_count' => $event->tickets_sold_count,
            ]);

        return Inertia::render('events/index', [
            'events' => $events,
            'filters' => [
                'name' => $request->string('name')->value(),
                'status' => $request->string('status')->value(),
                'visibility' => $request->string('visibility')->value(),
                'from' => $request->string('from')->value(),
                'to' => $request->string('to')->value(),
                'featured' => $request->boolean('featured'),
            ],
            'statuses' => collect(EventStatus::cases())
                ->map(fn (EventStatus $s) => ['value' => $s->value, 'label' => $s->label()])
                ->values(),
            'visibilities' => collect(EventVisibility::cases())
                ->map(fn (EventVisibility $v) => ['value' => $v->value, 'label' => $v->label()])
                ->values(),
            'categories' => EventCategory::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->map(fn (EventCategory $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                ])
                ->values(),
        ]);
    }

    public function show(
        string $current_team,
        Event $event,
        WeatherForecastService $weatherService,
    ): Response {
        $this->authoriseEvent($current_team, $event);

        $event->load([
            'category',
            'lineupArtists',
            'agendaEntries',
            'mediaItems',
            'ticketCategories.currencyPrices',
            'ticketCategories.discounts',
            'ticketCategories.promoCodes',
            'adCampaigns',
            'sponsors',
            'amenities',
        ]);

        return Inertia::render('events/show', [
            'event' => $this->eventDetailPayload($event),
            'weather' => $this->resolveWeatherForecast($event, $weatherService),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function resolveWeatherForecast(
        Event $event,
        WeatherForecastService $service,
    ): ?array {
        if ($event->latitude === null || $event->longitude === null || $event->starts_at === null) {
            return null;
        }

        $daysAhead = (int) round(now()->diffInDays($event->starts_at, false));
        if ($daysAhead < -1 || $daysAhead > 16) {
            return null;
        }

        $startDate = $event->starts_at->copy()->setTimezone($event->timezone)->format('Y-m-d');
        $endDate = $event->ends_at
            ? $event->ends_at->copy()->setTimezone($event->timezone)->format('Y-m-d')
            : $startDate;

        return $service->fetch(
            (float) $event->latitude,
            (float) $event->longitude,
            $startDate,
            $endDate,
            $event->timezone,
        );
    }

    public function edit(string $current_team, Event $event): Response
    {
        $this->authoriseEvent($current_team, $event);

        $event->load([
            'category',
            'lineupArtists',
            'agendaEntries',
            'mediaItems',
            'sponsors',
            'amenities',
        ]);

        return Inertia::render('events/edit', [
            'event' => $this->eventDetailPayload($event),
            'visibilities' => collect(EventVisibility::cases())
                ->map(fn (EventVisibility $v) => ['value' => $v->value, 'label' => $v->label()])
                ->values(),
            'statuses' => collect(EventStatus::cases())
                ->map(fn (EventStatus $s) => ['value' => $s->value, 'label' => $s->label()])
                ->values(),
            'categories' => EventCategory::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->map(fn (EventCategory $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                ])
                ->values(),
        ]);
    }

    public function store(
        SaveEventRequest $request,
        string $current_team,
        ImageProcessingService $imageService,
    ): RedirectResponse {
        $team = Team::where('slug', $current_team)->firstOrFail();

        $data = $request->safe()->except(['banner_image', 'lineup', 'agenda']);

        if ($request->hasFile('banner_image')) {
            $data['banner_image_path'] = $imageService->processAndStore(
                $request->file('banner_image'),
                'events/banners',
                ImageProcessingService::BANNER_SIZES,
            );
        }

        Event::create([
            ...$data,
            'organisation_id' => $team->uuid,
            'created_by_user_id' => $request->user()->id,
            'status' => EventStatus::Draft->value,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event created.')]);

        return to_route('events.index', ['current_team' => $current_team]);
    }

    public function update(
        SaveEventRequest $request,
        string $current_team,
        Event $event,
        ImageProcessingService $imageService,
    ): RedirectResponse {
        $this->authoriseEvent($current_team, $event);

        $data = $request->safe()->except([
            'banner_image',
            'lineup',
            'agenda',
            'sponsors',
            'amenities',
        ]);

        if ($request->hasFile('banner_image')) {
            if ($event->banner_image_path) {
                $imageService->delete($event->banner_image_path);
            }
            $data['banner_image_path'] = $imageService->processAndStore(
                $request->file('banner_image'),
                'events/banners',
                ImageProcessingService::BANNER_SIZES,
            );
        }

        DB::transaction(function () use ($event, $data, $request, $imageService) {
            $event->update($data);

            if ($request->has('lineup')) {
                $this->syncLineup($event, $request->validated('lineup') ?? []);
            }

            if ($request->has('agenda')) {
                $this->syncAgenda($event, $request->validated('agenda') ?? []);
            }

            if ($request->has('sponsors')) {
                $this->syncSponsors(
                    $event,
                    $request->validated('sponsors') ?? [],
                    $imageService,
                );
            }

            if ($request->has('amenities')) {
                $this->syncAmenities($event, $request->validated('amenities') ?? []);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event updated.')]);

        return to_route('events.show', [
            'current_team' => $current_team,
            'event' => $event->fresh()->slug,
        ]);
    }

    public function updateSeo(UpdateSeoRequest $request, string $current_team, Event $event): RedirectResponse
    {
        $this->authoriseEvent($current_team, $event);

        $event->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('SEO settings updated.')]);

        return back();
    }

    public function storeMedia(Request $request, string $current_team, Event $event): RedirectResponse
    {
        $this->authoriseEvent($current_team, $event);

        $data = $request->validate([
            'image' => [
                'required_without:video_url',
                'image',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:5120',
            ],
            'video_url' => [
                'required_without:image',
                'url',
                'max:2048',
            ],
            'caption' => ['nullable', 'string', 'max:280'],
        ]);

        $maxOrder = $event->mediaItems()->max('sort_order') ?? 0;
        $isFirst = $event->mediaItems()->count() === 0;

        if ($request->hasFile('image')) {
            $path = app(ImageProcessingService::class)->processAndStore(
                $request->file('image'),
                'events/gallery',
                ImageProcessingService::BANNER_SIZES,
            );
            EventMediaItem::create([
                'event_id' => $event->id,
                'type' => 'image',
                'path' => $path,
                'caption' => $data['caption'] ?? null,
                'is_primary' => $isFirst,
                'sort_order' => $maxOrder + 10,
            ]);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Image added.')]);
        } else {
            $url = $this->toEmbedUrl((string) $data['video_url']);
            EventMediaItem::create([
                'event_id' => $event->id,
                'type' => 'video',
                'url' => $url,
                'caption' => $data['caption'] ?? null,
                'is_primary' => $isFirst,
                'sort_order' => $maxOrder + 10,
            ]);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Video added.')]);
        }

        return back();
    }

    public function storeLineupPhoto(
        Request $request,
        string $current_team,
        Event $event,
        ImageProcessingService $imageService,
    ): JsonResponse {
        $this->authoriseEvent($current_team, $event);

        $request->validate([
            'photo' => [
                'required',
                'image',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:5120',
            ],
        ]);

        $path = $imageService->processAndStore(
            $request->file('photo'),
            'events/lineup',
            ImageProcessingService::SQUARE_SIZES,
        );

        return response()->json([
            'path' => $path,
            'url' => Storage::url($path),
        ]);
    }

    /**
     * Side-channel logo upload for the sponsor manager. Mirrors the lineup
     * photo endpoint — the manager component stuffs the returned path into
     * the hidden `sponsors[i][logo_path]` field on the main form.
     */
    public function storeSponsorLogo(
        Request $request,
        string $current_team,
        Event $event,
        ImageProcessingService $imageService,
    ): JsonResponse {
        $this->authoriseEvent($current_team, $event);

        $request->validate([
            'logo' => [
                'required',
                'image',
                'mimetypes:image/jpeg,image/png,image/webp,image/svg+xml',
                'max:5120',
            ],
        ]);

        $path = $imageService->processAndStore(
            $request->file('logo'),
            'events/sponsors',
            ImageProcessingService::SQUARE_SIZES,
        );

        return response()->json([
            'path' => $path,
            'url' => Storage::url($path),
        ]);
    }

    public function geocodeSearch(Request $request): JsonResponse
    {
        $query = trim((string) $request->string('q'));

        if (strlen($query) < 3) {
            return response()->json([]);
        }

        $cacheKey = 'geocode:'.md5(strtolower($query));

        // Serve from cache when available — this is also what satisfies Nominatim's
        // "no identical query within X seconds" policy; cached hits never reach Nominatim.
        if (Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey));
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    // Nominatim policy: User-Agent must identify the app + provide a contact address.
                    'User-Agent' => sprintf(
                        '%s/1.0 (%s)',
                        config('app.name', 'Discovery'),
                        config('mail.from.address', 'admin@example.com'),
                    ),
                    'Accept-Language' => 'en',
                    'Referer' => config('app.url'),
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'limit' => 8,
                ]);

            if (! $response->successful()) {
                // Do not cache — a transient Nominatim error should be retried next request.
                return response()->json([]);
            }

            $results = $response->json() ?? [];

            // Cache only confirmed successful responses (including legitimate empty result sets).
            Cache::put($cacheKey, $results, 86400);

            return response()->json($results);
        } catch (\Throwable) {
            // Network / timeout — return empty without caching so the next request retries.
            return response()->json([]);
        }
    }

    private function toEmbedUrl(string $url): string
    {
        if (preg_match('~youtube\.com/watch\?v=([\w-]+)~i', $url, $m)) {
            return "https://www.youtube.com/embed/{$m[1]}";
        }

        if (preg_match('~youtu\.be/([\w-]+)~i', $url, $m)) {
            return "https://www.youtube.com/embed/{$m[1]}";
        }

        if (preg_match('~vimeo\.com/(\d+)~i', $url, $m)) {
            return "https://player.vimeo.com/video/{$m[1]}";
        }

        return $url;
    }

    public function destroyMedia(
        string $current_team,
        Event $event,
        EventMediaItem $media,
        ImageProcessingService $imageService,
    ): RedirectResponse {
        $this->authoriseEvent($current_team, $event);
        abort_unless($media->event_id === $event->id, 404);

        if ($media->path) {
            $imageService->delete($media->path);
        }

        $media->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Image removed.')]);

        return back();
    }

    private function authoriseEvent(string $teamSlug, Event $event): void
    {
        $team = Team::where('slug', $teamSlug)->firstOrFail();
        abort_unless($event->organisation_id === $team->uuid, 403);
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function syncLineup(Event $event, array $entries): void
    {
        $existingIds = $event->lineupArtists()->pluck('id')->all();
        $submittedIds = collect($entries)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $toDelete = array_diff($existingIds, $submittedIds);
        if (! empty($toDelete)) {
            $event->lineupArtists()->whereIn('id', $toDelete)->delete();
        }

        foreach ($entries as $index => $entry) {
            $payload = [
                'event_id' => $event->id,
                'name' => $entry['name'],
                'role' => $entry['role'] ?? null,
                'bio' => $entry['bio'] ?? null,
                'image_path' => $entry['image_path'] ?? null,
                'social_url' => $entry['social_url'] ?? null,
                'is_headliner' => (bool) ($entry['is_headliner'] ?? false),
                'sort_order' => ($index + 1) * 10,
            ];

            if (! empty($entry['id'])) {
                $event->lineupArtists()
                    ->where('id', $entry['id'])
                    ->update($payload);
            } else {
                $event->lineupArtists()->create($payload);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function syncAgenda(Event $event, array $entries): void
    {
        $existingIds = $event->agendaEntries()->pluck('id')->all();
        $submittedIds = collect($entries)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $toDelete = array_diff($existingIds, $submittedIds);
        if (! empty($toDelete)) {
            $event->agendaEntries()->whereIn('id', $toDelete)->delete();
        }

        foreach ($entries as $index => $entry) {
            $payload = [
                'event_id' => $event->id,
                'starts_at' => $entry['starts_at'],
                'ends_at' => $entry['ends_at'] ?? null,
                'title' => $entry['title'],
                'description' => $entry['description'] ?? null,
                'host_name' => $entry['host_name'] ?? null,
                'host_role' => $entry['host_role'] ?? null,
                'sort_order' => ($index + 1) * 10,
            ];

            if (! empty($entry['id'])) {
                $event->agendaEntries()
                    ->where('id', $entry['id'])
                    ->update($payload);
            } else {
                $event->agendaEntries()->create($payload);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function syncSponsors(
        Event $event,
        array $entries,
        ImageProcessingService $imageService,
    ): void {
        $existing = $event->sponsors()->get(['id', 'logo_path'])->keyBy('id');
        $submittedIds = collect($entries)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        // Delete sponsors that were removed in the UI — and clean up their
        // logo files so we don't leak storage.
        foreach ($existing as $id => $row) {
            if (! in_array($id, $submittedIds, true)) {
                if ($row->logo_path) {
                    $imageService->delete($row->logo_path);
                }
                $event->sponsors()->where('id', $id)->delete();
            }
        }

        foreach ($entries as $index => $entry) {
            $payload = [
                'event_id' => $event->id,
                'name' => $entry['name'],
                'tier' => $entry['tier'],
                'logo_path' => $entry['logo_path'] ?? null,
                'website_url' => $entry['website_url'] ?? null,
                'social_url' => $entry['social_url'] ?? null,
                'description' => $entry['description'] ?? null,
                'sort_order' => ($index + 1) * 10,
            ];

            if (! empty($entry['id'])) {
                $row = $existing->get((int) $entry['id']);

                // If the user swapped logos, retire the previous one.
                if (
                    $row
                    && $row->logo_path
                    && $row->logo_path !== ($payload['logo_path'] ?? null)
                ) {
                    $imageService->delete($row->logo_path);
                }

                $event->sponsors()->where('id', $entry['id'])->update($payload);
            } else {
                $event->sponsors()->create($payload);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function syncAmenities(Event $event, array $entries): void
    {
        $existingIds = $event->amenities()->pluck('id')->all();
        $submittedIds = collect($entries)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $toDelete = array_diff($existingIds, $submittedIds);
        if (! empty($toDelete)) {
            $event->amenities()->whereIn('id', $toDelete)->delete();
        }

        foreach ($entries as $index => $entry) {
            $payload = [
                'event_id' => $event->id,
                'name' => $entry['name'],
                'icon' => $entry['icon'] ?? null,
                'description' => $entry['description'] ?? null,
                'category' => $entry['category'] ?? null,
                'is_highlighted' => (bool) ($entry['is_highlighted'] ?? false),
                'sort_order' => ($index + 1) * 10,
            ];

            if (! empty($entry['id'])) {
                $event->amenities()
                    ->where('id', $entry['id'])
                    ->update($payload);
            } else {
                $event->amenities()->create($payload);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function eventDetailPayload(Event $event): array
    {
        return [
            'id' => $event->id,
            'event_id' => $event->event_id,
            'slug' => $event->slug,
            'name' => $event->name,
            'description' => $event->description,
            'short_description' => $event->short_description,
            'status' => [
                'value' => $event->status->value,
                'label' => $event->status->label(),
            ],
            'visibility' => [
                'value' => $event->visibility->value,
                'label' => $event->visibility->label(),
            ],
            'is_featured' => $event->is_featured,
            'published_at' => $event->published_at?->toISOString(),
            'starts_at' => $event->starts_at?->toISOString(),
            'ends_at' => $event->ends_at?->toISOString(),
            'doors_open_at' => $event->doors_open_at?->toISOString(),
            'timezone' => $event->timezone,
            'sales_start_at' => $event->sales_start_at?->toISOString(),
            'sales_end_at' => $event->sales_end_at?->toISOString(),
            'venue_name' => $event->venue_name,
            'address_line_1' => $event->address_line_1,
            'address_line_2' => $event->address_line_2,
            'city' => $event->city,
            'region' => $event->region,
            'country_code' => $event->country_code,
            'postal_code' => $event->postal_code,
            'latitude' => $event->latitude !== null ? (float) $event->latitude : null,
            'longitude' => $event->longitude !== null ? (float) $event->longitude : null,
            'is_online' => $event->is_online,
            'online_url' => $event->online_url,
            'banner_image_url' => $event->banner_image_path
                ? Storage::url($event->banner_image_path)
                : null,
            'tags' => $event->tags,
            'capacity' => $event->capacity,
            'tickets_sold_count' => $event->tickets_sold_count,
            'minimum_age' => $event->minimum_age,
            'parking_info' => $event->parking_info,
            'age_requirement_details' => $event->age_requirement_details,
            'refund_policy' => $event->refund_policy,
            'terms' => $event->terms,
            'contact_email' => $event->contact_email,
            'contact_phone' => $event->contact_phone,
            'category' => $event->category
                ? [
                    'id' => $event->category->id,
                    'name' => $event->category->name,
                    'slug' => $event->category->slug,
                ]
                : null,
            'seo' => [
                'meta_title' => $event->meta_title,
                'meta_description' => $event->meta_description,
                'seo_keywords' => $event->seo_keywords,
                'robots_directive' => $event->robots_directive,
                'canonical_url' => $event->canonical_url,
                'og_title' => $event->og_title,
                'og_description' => $event->og_description,
                'og_image_path' => $event->og_image_path,
                'og_type' => $event->og_type,
                'og_locale' => $event->og_locale,
                'og_site_name' => $event->og_site_name,
                'twitter_card' => $event->twitter_card,
                'twitter_creator' => $event->twitter_creator,
                'twitter_title' => $event->twitter_title,
                'twitter_description' => $event->twitter_description,
                'twitter_image' => $event->twitter_image,
            ],
            'lineup' => $event->lineupArtists->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'role' => $a->role,
                'bio' => $a->bio,
                'image_path' => $a->image_path,
                'image_url' => $a->image_path
                    ? (str_starts_with($a->image_path, 'http')
                        ? $a->image_path
                        : Storage::url($a->image_path))
                    : null,
                'social_url' => $a->social_url,
                'is_headliner' => $a->is_headliner,
                'sort_order' => $a->sort_order,
            ])->values(),
            'agenda' => $event->agendaEntries->map(fn ($e) => [
                'id' => $e->id,
                'starts_at' => $e->starts_at?->toISOString(),
                'ends_at' => $e->ends_at?->toISOString(),
                'title' => $e->title,
                'description' => $e->description,
                'host_name' => $e->host_name,
                'host_role' => $e->host_role,
                'sort_order' => $e->sort_order,
            ])->values(),
            'media' => $event->mediaItems->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type,
                'url' => $m->url ?? ($m->path ? Storage::url($m->path) : null),
                'caption' => $m->caption,
                'is_primary' => $m->is_primary,
            ])->values(),
            'ticket_categories' => $event->ticketCategories->map(fn ($cat) => [
                'id' => $cat->id,
                'uuid' => $cat->uuid,
                'name' => $cat->name,
                'description' => $cat->description,
                'image_url' => $cat->image_path
                    ? Storage::url($cat->image_path)
                    : null,
                'offline_quantity' => $cat->offline_quantity,
                'online_quantity' => $cat->online_quantity,
                'base_price' => $cat->base_price !== null
                    ? (float) $cat->base_price
                    : 0.0,
                'base_currency' => $cat->base_currency ?? 'USD',
                'min_per_order' => $cat->min_per_order,
                'max_per_order' => $cat->max_per_order,
                'is_visible' => (bool) $cat->is_visible,
                'sales_start_at' => $cat->sales_start_at?->toISOString(),
                'sales_end_at' => $cat->sales_end_at?->toISOString(),
                'generation_status' => $cat->generation_status?->value,
                'generation_progress' => $cat->generation_progress,
                'sale_status' => [
                    'value' => $cat->sale_status->value,
                    'label' => $cat->sale_status->label(),
                ],
                'admission_type' => $cat->admission_type
                    ? ['value' => $cat->admission_type->value, 'label' => $cat->admission_type->label()]
                    : null,
                'pass_type' => $cat->pass_type
                    ? ['value' => $cat->pass_type->value, 'label' => $cat->pass_type->label()]
                    : null,
                'currency_prices' => $cat->currencyPrices->map(fn ($p) => [
                    'id' => $p->id,
                    'currency' => $p->currency_code,
                    'price' => (float) $p->price,
                ])->values(),
                'discounts' => $cat->discounts->map(fn ($d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'type' => $d->type,
                    'value' => (float) $d->value,
                    'max_uses' => $d->max_uses,
                    'starts_at' => $d->starts_at?->toISOString(),
                    'ends_at' => $d->ends_at?->toISOString(),
                ])->values(),
                'promo_codes' => $cat->promoCodes->map(fn ($p) => [
                    'id' => $p->id,
                    'code' => $p->code,
                    'type' => $p->type,
                    'value' => (float) $p->value,
                    'max_uses' => $p->max_uses,
                    'starts_at' => $p->starts_at?->toISOString(),
                    'ends_at' => $p->ends_at?->toISOString(),
                ])->values(),
                'sort_order' => $cat->sort_order,
            ])->values(),
            'sponsors' => $event->sponsors
                ->sortBy([
                    fn ($a, $b) => $a->tier->rank() <=> $b->tier->rank(),
                    ['sort_order', 'asc'],
                ])
                ->values()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'tier' => [
                        'value' => $s->tier->value,
                        'label' => $s->tier->label(),
                        'rank' => $s->tier->rank(),
                    ],
                    'logo_path' => $s->logo_path,
                    'logo_url' => $s->logo_path
                        ? (str_starts_with($s->logo_path, 'http')
                            ? $s->logo_path
                            : Storage::url($s->logo_path))
                        : null,
                    'website_url' => $s->website_url,
                    'social_url' => $s->social_url,
                    'description' => $s->description,
                    'sort_order' => $s->sort_order,
                ])
                ->values(),
            'amenities' => $event->amenities->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'icon' => $a->icon,
                'description' => $a->description,
                'category' => $a->category,
                'is_highlighted' => (bool) $a->is_highlighted,
                'sort_order' => $a->sort_order,
            ])->values(),
            'sponsor_tiers' => collect(SponsorTier::cases())->map(fn (SponsorTier $t) => [
                'value' => $t->value,
                'label' => $t->label(),
                'rank' => $t->rank(),
            ])->values(),
            'ad_campaigns' => $event->adCampaigns->map(fn ($c) => [
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
                'metrics' => $c->metrics,
            ])->values(),
        ];
    }
}
