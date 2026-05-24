<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Month-grid calendar of every event in the active org. Default view is
 * the current month; `?month=YYYY-MM` jumps to a specific window.
 * Click an event → its detail page.
 */
class CalendarController extends Controller
{
    public function index(Request $request, string $current_organization): Response
    {
        abort_unless($request->user()->can('event.view'), 403);

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        $monthArg = (string) $request->query('month', '');
        $cursor = $this->parseMonth($monthArg) ?? CarbonImmutable::now()->startOfMonth();

        // Show a full 6-row grid: from the first Sunday on/before the
        // 1st through the last Saturday on/after the last day.
        $gridStart = $cursor->startOfMonth()->startOfWeek(CarbonImmutable::SUNDAY);
        $gridEnd = $cursor->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY);

        $events = Event::query()
            ->where('organisation_id', $org->id)
            ->whereBetween('starts_at', [$gridStart, $gridEnd])
            ->orderBy('starts_at')
            ->get(['id', 'slug', 'name', 'starts_at', 'ends_at', 'status', 'venue_name', 'city'])
            ->map(fn (Event $e) => [
                'slug' => $e->slug,
                'name' => $e->name,
                'starts_at' => $e->starts_at?->toIso8601String(),
                'date' => $e->starts_at?->format('Y-m-d'),
                'time' => $e->starts_at?->format('H:i'),
                'status' => $e->status,
                'venue' => trim(($e->venue_name ?? '').($e->city ? ', '.$e->city : '')) ?: null,
            ])
            ->all();

        // Build the 6-row grid as a flat list of cells the React side
        // chunks into weeks. Each cell carries date + isCurrentMonth.
        $days = [];
        for ($d = $gridStart; $d->lte($gridEnd); $d = $d->addDay()) {
            $days[] = [
                'date' => $d->format('Y-m-d'),
                'day' => (int) $d->format('j'),
                'is_current_month' => $d->month === $cursor->month,
                'is_today' => $d->isSameDay(now()),
            ];
        }

        return Inertia::render('calendar/index', [
            'cursor' => $cursor->format('Y-m'),
            'cursor_label' => $cursor->format('F Y'),
            'prev_month' => $cursor->subMonth()->format('Y-m'),
            'next_month' => $cursor->addMonth()->format('Y-m'),
            'today_month' => CarbonImmutable::now()->format('Y-m'),
            'days' => $days,
            'events' => $events,
            'breadcrumbs' => [
                ['title' => 'Calendar', 'href' => "/{$current_organization}/calendar"],
            ],
        ]);
    }

    protected function parseMonth(string $arg): ?CarbonImmutable
    {
        if ($arg === '' || ! preg_match('/^\d{4}-\d{2}$/', $arg)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m', $arg)->startOfMonth();
        } catch (\Throwable) {
            return null;
        }
    }
}
