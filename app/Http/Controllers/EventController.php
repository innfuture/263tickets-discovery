<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Http\Requests\Events\SaveEventRequest;
use App\Http\Requests\Events\UpdateSeoRequest;
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

        $data = $request->safe()->except(['banner_image', 'lineup', 'agenda']);

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

        DB::transaction(function () use ($event, $data, $request) {
            $event->update($data);

            if ($request->has('lineup')) {
                $this->syncLineup($event, $request->validated('lineup') ?? []);
            }

            if ($request->has('agenda')) {
                $this->syncAgenda($event, $request->validated('agenda') ?? []);
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

    public function geocodeSearch(Request $request): JsonResponse
    {
        $query = trim((string) $request->string('q'));

        if (strlen($query) < 3) {
            return response()->json([]);
        }

        $cacheKey = 'geocode:'.md5(strtolower($query));

        $results = Cache::remember($cacheKey, 86400, function () use ($query) {
            try {
                $response = Http::timeout(5)
                    ->withHeaders([
                        'User-Agent' => config('app.name', 'Discovery').' Event Discovery (admin)',
                        'Accept-Language' => 'en',
                    ])
                    ->get('https://nominatim.openstreetmap.org/search', [
                        'q' => $query,
                        'format' => 'jsonv2',
                        'addressdetails' => 1,
                        'limit' => 8,
                    ]);

                return $response->successful() ? $response->json() : [];
            } catch (\Throwable) {
                return [];
            }
        });

        return response()->json($results);
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
                'canonical_url' => $event->canonical_url,
                'og_title' => $event->og_title,
                'og_description' => $event->og_description,
                'og_image_path' => $event->og_image_path,
                'twitter_card' => $event->twitter_card,
                'twitter_creator' => $event->twitter_creator,
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
        ];
    }
}
