<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventPageView;
use App\Models\OfflineTicket;
use App\Models\TicketCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Org-wide analytics rollup — the cross-event view EventAnalyticsController
 * doesn't give you. Time window is selectable (7d/30d/90d, defaults to 30d).
 *
 * Surfaces five blocks:
 *   • KPI strip (views, unique sessions, conversions, conversion rate)
 *   • Daily views trend
 *   • Conversion funnel (views → clicks → conversions)
 *   • Traffic sources (utm_source / referrer)
 *   • Per-event leaderboard (views, sell-through, scan rate)
 */
class ReportsController extends Controller
{
    public function index(Request $request, string $current_organization): Response
    {
        abort_unless($request->user()->can('analytics.view-org'), 403);

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        $rangeDays = in_array((int) $request->query('range', 30), [7, 30, 90], true)
            ? (int) $request->query('range', 30)
            : 30;
        $from = now()->subDays($rangeDays)->startOfDay();

        $eventIds = Event::query()->where('organisation_id', $org->id)->pluck('id');

        // KPI strip
        $totals = EventPageView::query()
            ->whereIn('event_id', $eventIds)
            ->where('created_at', '>=', $from)
            ->selectRaw('
                COUNT(CASE WHEN event_type = "page_view" THEN 1 END) AS views,
                COUNT(DISTINCT session_id) AS sessions,
                COUNT(CASE WHEN event_type = "click" THEN 1 END) AS clicks,
                COUNT(CASE WHEN event_type = "conversion" THEN 1 END) AS conversions
            ')
            ->first();

        $views = (int) ($totals->views ?? 0);
        $sessions = (int) ($totals->sessions ?? 0);
        $clicks = (int) ($totals->clicks ?? 0);
        $conversions = (int) ($totals->conversions ?? 0);
        $convRate = $views > 0 ? round(($conversions / $views) * 100, 2) : 0.0;

        // Daily trend
        $trend = EventPageView::query()
            ->whereIn('event_id', $eventIds)
            ->where('created_at', '>=', $from)
            ->where('event_type', 'page_view')
            ->selectRaw('DATE(created_at) AS d, COUNT(*) AS views')
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->map(fn ($r) => ['date' => (string) $r->d, 'views' => (int) $r->views])
            ->all();

        // Traffic sources
        $sources = EventPageView::query()
            ->whereIn('event_id', $eventIds)
            ->where('created_at', '>=', $from)
            ->where('event_type', 'page_view')
            ->selectRaw('COALESCE(NULLIF(utm_source, ""), NULLIF(referrer, ""), "Direct") AS source, COUNT(*) AS hits')
            ->groupBy('source')
            ->orderByDesc('hits')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['source' => (string) $r->source, 'hits' => (int) $r->hits])
            ->all();

        // Per-event leaderboard
        $leaderboard = EventPageView::query()
            ->whereIn('event_id', $eventIds)
            ->where('event_type', 'page_view')
            ->where('created_at', '>=', $from)
            ->selectRaw('event_id, COUNT(*) AS views')
            ->groupBy('event_id')
            ->orderByDesc('views')
            ->limit(15)
            ->get()
            ->map(function ($row) {
                $event = Event::find($row->event_id, ['id', 'slug', 'name']);
                if ($event === null) {
                    return null;
                }
                $capacity = (int) TicketCategory::where('event_id', $event->id)
                    ->sum(DB::raw('offline_quantity + online_quantity'));
                // Sold = issued non-voided offline tickets for the event.
                $sold = (int) OfflineTicket::where('event_id', $event->id)
                    ->where('is_voided', false)
                    ->count();
                $issued = (int) OfflineTicket::where('event_id', $event->id)->count();
                $scanned = (int) OfflineTicket::where('event_id', $event->id)->whereNotNull('scanned_at')->count();

                return [
                    'slug' => $event->slug,
                    'name' => $event->name,
                    'views' => (int) $row->views,
                    'capacity' => $capacity,
                    'sold' => $sold,
                    'sell_through' => $capacity > 0 ? round($sold / $capacity * 100, 1) : 0.0,
                    'issued' => $issued,
                    'scanned' => $scanned,
                    'scan_rate' => $issued > 0 ? round($scanned / $issued * 100, 1) : 0.0,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return Inertia::render('reports/index', [
            'range_days' => $rangeDays,
            'kpis' => [
                'views' => $views,
                'sessions' => $sessions,
                'clicks' => $clicks,
                'conversions' => $conversions,
                'conv_rate' => $convRate,
            ],
            'trend' => $trend,
            'sources' => $sources,
            'leaderboard' => $leaderboard,
            'permissions' => [
                'can_export' => $request->user()->can('analytics.export'),
            ],
            'breadcrumbs' => [
                ['title' => 'Reports', 'href' => "/{$current_organization}/reports"],
            ],
        ]);
    }
}
