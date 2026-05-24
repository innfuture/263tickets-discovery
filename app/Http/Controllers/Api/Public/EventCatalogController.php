<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Services\Storefront\EventPageViewTracker;
use App\Services\Storefront\PublicEventQuery;
use App\Services\Storefront\TicketReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Public read-only catalog. No auth, rate-limited by IP. Powers the
 * marketing-site events list and detail page.
 *
 * GET  /api/v1/public/events
 * GET  /api/v1/public/events/featured
 * GET  /api/v1/public/events/{slug}
 */
class EventCatalogController extends Controller
{
    public function __construct(
        protected PublicEventQuery $query,
        protected TicketReservation $reservation,
        protected EventPageViewTracker $pageViews,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(
            (int) $request->integer('per_page', (int) config('storefront.discovery.default_page_size', 20)),
            (int) config('storefront.discovery.max_page_size', 100),
        );

        $cacheKey = 'storefront:events:index:'.md5($request->fullUrl().'|pp='.$perPage);
        $ttl = (int) config('storefront.discovery.list_cache_seconds', 30);

        $payload = $ttl > 0
            ? Cache::tags(['storefront-events'])->remember($cacheKey, $ttl, fn () => $this->buildIndex($request, $perPage))
            : $this->buildIndex($request, $perPage);

        return response()->json($payload);
    }

    public function featured(): JsonResponse
    {
        $ttl = (int) config('storefront.discovery.list_cache_seconds', 30);
        $payload = $ttl > 0
            ? Cache::tags(['storefront-events'])->remember('storefront:events:featured', $ttl, fn () => [
                'data' => $this->query->featured()
                    ->map(fn ($event) => $this->summary($event))
                    ->all(),
            ])
            : ['data' => $this->query->featured()->map(fn ($event) => $this->summary($event))->all()];

        return response()->json($payload);
    }

    /** @return array<string, mixed> */
    protected function buildIndex(Request $request, int $perPage): array
    {
        $page = $this->query->paginate(
            search: $request->query('search'),
            city: $request->query('city'),
            countryCode: $request->query('country'),
            categorySlug: $request->query('category'),
            organizerSlug: $request->query('organizer'),
            startsAfter: $request->query('starts_after'),
            startsBefore: $request->query('starts_before'),
            sort: $request->query('sort', 'starts_at'),
            perPage: $perPage,
        );

        return [
            'data' => $page->getCollection()->map(fn ($event) => $this->summary($event))->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ];
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $event = $this->query->findPublic($slug);
        if (! $event) {
            return response()->json(['error' => 'event_not_found'], 404);
        }

        // Fire-and-forget analytics — failure should never surface to
        // the buyer or block the response.
        try {
            $this->pageViews->record($event, $request);
        } catch (\Throwable) {
            // Intentionally swallowed.
        }

        return response()->json([
            'data' => [
                'event_id' => $event->event_id,
                'slug' => $event->slug,
                'name' => $event->name,
                'description' => $event->description,
                'short_description' => $event->short_description,
                'status' => $event->status?->value,
                'visibility' => $event->visibility?->value,
                'starts_at' => optional($event->starts_at)->toIso8601String(),
                'ends_at' => optional($event->ends_at)->toIso8601String(),
                'doors_open_at' => optional($event->doors_open_at)->toIso8601String(),
                'sales_start_at' => optional($event->sales_start_at)->toIso8601String(),
                'sales_end_at' => optional($event->sales_end_at)->toIso8601String(),
                'timezone' => $event->timezone,
                'is_online' => (bool) $event->is_online,
                'online_url' => $event->is_online ? $event->online_url : null,
                'venue' => [
                    'name' => $event->venue_name,
                    'address_line_1' => $event->address_line_1,
                    'address_line_2' => $event->address_line_2,
                    'city' => $event->city,
                    'region' => $event->region,
                    'country_code' => $event->country_code,
                    'postal_code' => $event->postal_code,
                    'latitude' => $event->latitude,
                    'longitude' => $event->longitude,
                ],
                'banner_image_path' => $event->banner_image_path,
                'capacity' => $event->capacity,
                'tickets_sold' => (int) $event->tickets_sold_count,
                'is_sold_out' => $event->isSoldOut(),
                'minimum_age' => $event->minimum_age,
                'refund_policy' => $event->refund_policy,
                'organization' => $this->organizationBlock($event),
                'category' => $event->category ? [
                    'slug' => $event->category->slug ?? null,
                    'name' => $event->category->name ?? null,
                ] : null,
                'lineup' => $event->lineupArtists->map(fn ($a) => [
                    'name' => $a->name,
                    'is_headliner' => (bool) $a->is_headliner,
                ])->all(),
                'agenda' => $event->agendaEntries->map(fn ($a) => [
                    'title' => $a->title,
                    'starts_at' => optional($a->starts_at)->toIso8601String(),
                    'ends_at' => optional($a->ends_at)->toIso8601String(),
                ])->all(),
                'amenities' => $event->amenities->map(fn ($a) => [
                    'label' => $a->label ?? null,
                    'is_highlighted' => (bool) ($a->is_highlighted ?? false),
                ])->all(),
                'sponsors' => $event->sponsors->map(fn ($s) => [
                    'name' => $s->name ?? null,
                    'logo_path' => $s->logo_path ?? null,
                ])->all(),
                'quantity_discounts' => $this->quantityDiscountHints($event),
                'tickets' => $event->ticketCategories->map(fn ($cat) => [
                    'uuid' => $cat->uuid,
                    'id' => (int) $cat->id,
                    'name' => $cat->name,
                    'description' => $cat->description,
                    'pass_type' => $cat->pass_type?->value,
                    'admission_type' => $cat->admission_type?->value,
                    'base_price' => $cat->base_price,
                    'base_currency' => $cat->base_currency,
                    'min_per_order' => (int) ($cat->min_per_order ?? 1),
                    'max_per_order' => (int) ($cat->max_per_order ?? 0),
                    'sales_start_at' => optional($cat->sales_start_at)->toIso8601String(),
                    'sales_end_at' => optional($cat->sales_end_at)->toIso8601String(),
                    'available' => $this->reservation->availabilityForCategory($cat),
                    'currency_prices' => $cat->currencyPrices->map(fn ($p) => [
                        'currency' => $p->currency_code,
                        'price' => $p->price,
                    ])->all(),
                ])->all(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    protected function summary($event): array
    {
        return [
            'event_id' => $event->event_id,
            'slug' => $event->slug,
            'name' => $event->name,
            'short_description' => $event->short_description,
            'banner_image_path' => $event->banner_image_path,
            'starts_at' => optional($event->starts_at)->toIso8601String(),
            'ends_at' => optional($event->ends_at)->toIso8601String(),
            'city' => $event->city,
            'country_code' => $event->country_code,
            'is_sold_out' => $event->isSoldOut(),
            'is_featured' => (bool) $event->is_featured,
        ];
    }

    /**
     * Snapshot of currently-valid QuantityDiscountRules so the buyer
     * UI can render "Buy N more for X% off" hints. Read-only mirror of
     * what `QuantityDiscountResolver` applies at price-compute time.
     *
     * @return list<array<string, mixed>>
     */
    protected function quantityDiscountHints($event): array
    {
        if (! class_exists(\App\Models\QuantityDiscountRule::class)) {
            return [];
        }

        return \App\Models\QuantityDiscountRule::query()
            ->where('organisation_id', $event->organisation_id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('event_id')->orWhere('event_id', $event->id))
            ->get()
            ->filter(fn ($r) => $r->isCurrentlyValid())
            ->map(fn ($r) => [
                'name' => $r->name,
                'ticket_category_id' => $r->ticket_category_id,
                'min_quantity' => (int) $r->min_quantity,
                'discount_percent' => $r->discount_percent !== null ? (int) $r->discount_percent : null,
                'discount_fixed_cents' => $r->discount_fixed_cents !== null ? (int) $r->discount_fixed_cents : null,
                'free_quantity' => $r->free_quantity !== null ? (int) $r->free_quantity : null,
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null */
    protected function organizationBlock($event): ?array
    {
        $org = $event->organisation;
        if (! $org) {
            return null;
        }

        return [
            'uuid' => $org->uuid,
            'slug' => $org->slug,
            'name' => $org->brand_name ?: $org->name,
            'logo_url' => method_exists($org, 'logoUrl') ? $org->logoUrl() : null,
            'is_verified' => (bool) ($org->is_verified ?? false),
        ];
    }
}
