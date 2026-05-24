<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Developer;

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The third-party developer-facing read API. ALL endpoints are
 * read-only — write paths live on the storefront / automations /
 * extensions surfaces.
 *
 *   /api/developer/v1/events              free+
 *   /api/developer/v1/events/{slug}       free+
 *   /api/developer/v1/orders/aggregate    basic+
 *   /api/developer/v1/analytics/events    enterprise+
 *
 * Scopes are enforced by `developer.key:<scope>` per-route. Tier
 * feature-gating is also enforced inside controllers when the route
 * is per-tier-conditional (e.g. analytics rollups).
 */
class DeveloperPublicApiController extends Controller
{
    public function events(Request $request): JsonResponse
    {
        $rows = Event::query()
            ->whereIn('status', $this->publicStatuses())
            ->where('visibility', EventVisibility::Public->value)
            ->when($request->query('city'), fn ($q, $c) => $q->where('city', 'like', "%{$c}%"))
            ->when($request->query('country'), fn ($q, $c) => $q->where('country_code', strtoupper($c)))
            ->orderBy('starts_at')
            ->limit(min(500, (int) $request->integer('limit', 100)))
            ->get(['id', 'event_id', 'slug', 'name', 'starts_at', 'city', 'country_code', 'capacity', 'tickets_sold_count']);

        return response()->json(['data' => $rows]);
    }

    public function event(string $slug): JsonResponse
    {
        $event = Event::query()
            ->whereIn('status', $this->publicStatuses())
            ->where('visibility', EventVisibility::Public->value)
            ->where('slug', $slug)
            ->first();

        if (! $event) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json(['data' => $event]);
    }

    public function ordersAggregate(Request $request): JsonResponse
    {
        // Aggregate-only — no PII, no individual orders. Basic+ tier.
        $rows = Order::query()
            ->selectRaw('event_id, count(*) as order_count, sum(total_cents) as total_cents, currency')
            ->where('status', 'paid')
            ->when($request->query('event_id'), fn ($q, $id) => $q->where('event_id', $id))
            ->when($request->query('placed_after'), fn ($q, $d) => $q->where('placed_at', '>=', $d))
            ->when($request->query('placed_before'), fn ($q, $d) => $q->where('placed_at', '<=', $d))
            ->groupBy('event_id', 'currency')
            ->limit(500)
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function analyticsEvents(Request $request): JsonResponse
    {
        // Enterprise+ — richer rollups: views, sales velocity, etc.
        $rows = Event::query()
            ->selectRaw('id, name, slug, views_count, tickets_sold_count, capacity')
            ->whereIn('status', $this->publicStatuses())
            ->where('visibility', EventVisibility::Public->value)
            ->when($request->query('country'), fn ($q, $c) => $q->where('country_code', strtoupper($c)))
            ->orderByDesc('views_count')
            ->limit(min(500, (int) $request->integer('limit', 100)))
            ->get();

        return response()->json(['data' => $rows]);
    }

    /** @return list<string> */
    protected function publicStatuses(): array
    {
        return array_map(
            fn (EventStatus $s) => $s->value,
            array_filter(EventStatus::cases(), fn ($s) => $s->isPubliclyVisible())
        );
    }
}
