<?php

namespace App\Http\Controllers;

use App\Http\Middleware\TrackEventPageView;
use App\Models\Event;
use App\Models\EventPageView;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventAnalyticsController extends Controller
{
    public function index(string $current_team, Event $event): JsonResponse
    {
        $this->authoriseEvent($current_team, $event);

        $days = 30;
        $from = now()->subDays($days)->startOfDay();

        // Daily page views
        $dailyViews = EventPageView::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as views'),
        )
            ->where('event_id', $event->id)
            ->where('event_type', 'page_view')
            ->where('created_at', '>=', $from)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => ['date' => $r->date, 'views' => (int) $r->views])
            ->values();

        // Total metrics
        $totals = EventPageView::where('event_id', $event->id)
            ->selectRaw('
                COUNT(CASE WHEN event_type = "page_view" THEN 1 END) as total_views,
                COUNT(DISTINCT session_id) as unique_sessions,
                COUNT(CASE WHEN event_type = "conversion" THEN 1 END) as conversions,
                COUNT(CASE WHEN event_type = "click" THEN 1 END) as clicks
            ')
            ->first();

        // Top referrers
        $topReferrers = EventPageView::select(
            DB::raw('COALESCE(utm_source, referrer, "Direct") as source'),
            DB::raw('COUNT(*) as visits'),
        )
            ->where('event_id', $event->id)
            ->where('event_type', 'page_view')
            ->where('created_at', '>=', $from)
            ->groupBy('source')
            ->orderByDesc('visits')
            ->limit(8)
            ->get()
            ->map(fn ($r) => ['source' => $r->source, 'visits' => (int) $r->visits])
            ->values();

        // Top countries
        $topCountries = EventPageView::select(
            DB::raw('COALESCE(country_code, "Unknown") as country_code'),
            DB::raw('COUNT(*) as views'),
        )
            ->where('event_id', $event->id)
            ->where('event_type', 'page_view')
            ->where('created_at', '>=', $from)
            ->whereNotNull('country_code')
            ->groupBy('country_code')
            ->orderByDesc('views')
            ->limit(8)
            ->get()
            ->map(fn ($r) => ['country_code' => $r->country_code, 'views' => (int) $r->views])
            ->values();

        return response()->json([
            'period_days' => $days,
            'totals' => [
                'page_views' => (int) ($totals->total_views ?? 0),
                'unique_sessions' => (int) ($totals->unique_sessions ?? 0),
                'conversions' => (int) ($totals->conversions ?? 0),
                'clicks' => (int) ($totals->clicks ?? 0),
            ],
            'daily_views' => $dailyViews,
            'top_referrers' => $topReferrers,
            'top_countries' => $topCountries,
        ]);
    }

    public function track(Request $request, string $current_team, Event $event): JsonResponse
    {
        $this->authoriseEvent($current_team, $event);

        $data = $request->validate([
            'event_type' => ['required', 'string', 'in:click,conversion,view_section,share,bookmark'],
            'target' => ['nullable', 'string', 'max:255'],
            'value' => ['nullable', 'numeric'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            EventPageView::create([
                'event_id' => $event->id,
                'session_id' => substr(session()->getId() ?? '', 0, 64),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'referrer' => $request->headers->get('Referer')
                    ? substr($request->headers->get('Referer'), 0, 2048)
                    : null,
                'country_code' => TrackEventPageView::detectCountry($request),
                'event_type' => $data['event_type'],
                'event_target' => $data['target'] ?? null,
                'event_value' => $data['value'] ?? null,
                'event_metadata' => $data['metadata'] ?? null,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Tracking failures must never surface to the user.
        }

        return response()->json(['ok' => true]);
    }

    private function authoriseEvent(string $teamSlug, Event $event): void
    {
        $team = Team::where('slug', $teamSlug)->firstOrFail();
        abort_unless($event->organisation_id === $team->uuid, 403);
    }
}
