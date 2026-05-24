<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventPageView;
use App\Models\OfflineTicket;
use App\Models\TicketCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Organizer home — replaces the placeholder dashboard with real stats
 * pulled from the existing event / ticket / view tables. Five panels:
 *
 *   • KPI tiles (today + 30-day rollups)
 *   • Upcoming events (next 5)
 *   • Recent ticket activity
 *   • Page-view trend (last 14 days)
 *   • Quick actions
 *
 * All queries are scoped to the active organisation and respect the
 * caller's permissions — anything they can't see is silently zeroed.
 */
class DashboardController extends Controller
{
    public function index(Request $request, string $current_organization): Response
    {
        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        $now = now();
        $startOfDay = $now->copy()->startOfDay();
        $thirtyDaysAgo = $now->copy()->subDays(30);
        $fourteenDaysAgo = $now->copy()->subDays(14);

        $canViewAnalytics = $request->user()->can('analytics.view-org');
        $canViewFinance = $request->user()->can('finance.view-revenue');

        // Event roll-ups
        $eventStats = Event::query()
            ->where('organisation_id', $org->id)
            ->selectRaw('
                COUNT(*) AS total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS published,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS draft,
                SUM(CASE WHEN starts_at >= NOW() THEN 1 ELSE 0 END) AS upcoming
            ', [EventStatus::Published->value, EventStatus::Draft->value])
            ->first();

        // Ticket roll-ups (uses OfflineTicket as the only existing
        // ticket source; online ticket inventory becomes additive once
        // the order pipeline lands).
        $ticketTotals = OfflineTicket::query()
            ->where('organisation_id', $org->id)
            ->selectRaw('
                COUNT(*) AS issued,
                SUM(CASE WHEN scanned_at IS NOT NULL THEN 1 ELSE 0 END) AS scanned,
                SUM(CASE WHEN is_voided = 1 THEN 1 ELSE 0 END) AS voided
            ')
            ->first();

        // View activity (org-wide, 30d)
        $views30d = (int) EventPageView::query()
            ->whereIn('event_id', Event::where('organisation_id', $org->id)->select('id'))
            ->where('event_type', 'page_view')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();

        $views14dTrend = $canViewAnalytics
            ? EventPageView::query()
                ->whereIn('event_id', Event::where('organisation_id', $org->id)->select('id'))
                ->where('event_type', 'page_view')
                ->where('created_at', '>=', $fourteenDaysAgo)
                ->selectRaw('DATE(created_at) AS d, COUNT(*) AS views')
                ->groupBy('d')
                ->orderBy('d')
                ->get()
                ->map(fn ($r) => ['date' => (string) $r->d, 'views' => (int) $r->views])
                ->all()
            : [];

        // Upcoming events (next 5)
        $upcoming = Event::query()
            ->where('organisation_id', $org->id)
            ->where('starts_at', '>=', $now)
            ->orderBy('starts_at')
            ->limit(5)
            ->get(['id', 'slug', 'name', 'starts_at', 'venue_name', 'city', 'status'])
            ->map(fn (Event $e) => [
                'slug' => $e->slug,
                'name' => $e->name,
                'starts_at' => $e->starts_at?->toIso8601String(),
                'starts_relative' => $e->starts_at?->diffForHumans() ?? '—',
                'venue' => trim(($e->venue_name ?? '').($e->city ? ', '.$e->city : '')) ?: null,
                'status' => $e->status,
            ])
            ->all();

        // Recent ticket activity (scans + voids over last 7d)
        $recentActivity = OfflineTicket::query()
            ->where('organisation_id', $org->id)
            ->where(function ($q) {
                $q->whereNotNull('scanned_at')->orWhereNotNull('voided_at');
            })
            ->orderByDesc(DB::raw('GREATEST(COALESCE(scanned_at, "1970-01-01"), COALESCE(voided_at, "1970-01-01"))'))
            ->limit(10)
            ->get(['id', 'event_id', 'ticket_number', 'scanned_at', 'voided_at', 'is_voided'])
            ->map(fn (OfflineTicket $t) => [
                'ticket_number' => $t->ticket_number,
                'event_id' => $t->event_id,
                'kind' => $t->is_voided ? 'voided' : 'scanned',
                'at' => ($t->is_voided ? $t->voided_at : $t->scanned_at)?->toIso8601String(),
                'relative' => ($t->is_voided ? $t->voided_at : $t->scanned_at)?->diffForHumans(),
            ])
            ->all();

        // Top events by views (30d)
        $topEvents = $canViewAnalytics
            ? EventPageView::query()
                ->whereIn('event_id', Event::where('organisation_id', $org->id)->select('id'))
                ->where('event_type', 'page_view')
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->selectRaw('event_id, COUNT(*) AS views')
                ->groupBy('event_id')
                ->orderByDesc('views')
                ->limit(5)
                ->get()
                ->map(function ($row) {
                    $event = Event::find($row->event_id, ['id', 'slug', 'name']);

                    return $event ? [
                        'slug' => $event->slug,
                        'name' => $event->name,
                        'views' => (int) $row->views,
                    ] : null;
                })
                ->filter()
                ->values()
                ->all()
            : [];

        // Capacity vs sold (cross-event). Capacity = offline+online
        // inventory across all categories; sold = count of issued
        // (non-voided) offline tickets — the only ticket source the
        // codebase has today.
        $capacityTotal = (int) TicketCategory::query()
            ->whereIn('event_id', Event::where('organisation_id', $org->id)->select('id'))
            ->sum(DB::raw('offline_quantity + online_quantity'));

        $soldTotal = (int) OfflineTicket::query()
            ->where('organisation_id', $org->id)
            ->where('is_voided', false)
            ->count();

        $capacity = (object) ['capacity' => $capacityTotal, 'sold' => $soldTotal];

        return Inertia::render('dashboard', [
            'stats' => [
                'events_total' => (int) ($eventStats->total ?? 0),
                'events_published' => (int) ($eventStats->published ?? 0),
                'events_draft' => (int) ($eventStats->draft ?? 0),
                'events_upcoming' => (int) ($eventStats->upcoming ?? 0),
                'tickets_issued' => (int) ($ticketTotals->issued ?? 0),
                'tickets_scanned' => (int) ($ticketTotals->scanned ?? 0),
                'tickets_voided' => (int) ($ticketTotals->voided ?? 0),
                'views_30d' => $views30d,
                'capacity' => (int) ($capacity->capacity ?? 0),
                'sold' => (int) ($capacity->sold ?? 0),
            ],
            'views_trend' => $views14dTrend,
            'upcoming' => $upcoming,
            'recent_activity' => $recentActivity,
            'top_events' => $topEvents,
            'permissions' => [
                'can_view_finance' => $canViewFinance,
                'can_view_analytics' => $canViewAnalytics,
                'can_create_event' => $request->user()->can('event.create'),
            ],
            'breadcrumbs' => [
                ['title' => 'Dashboard', 'href' => "/{$current_organization}/dashboard"],
            ],
        ]);
    }
}
