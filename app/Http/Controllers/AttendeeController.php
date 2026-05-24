<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\OfflineTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cross-event attendee roster. The current ticket model carries no
 * buyer profile — tickets are identified by `ticket_number` only —
 * so the roster surface what we *do* have (ticket number, event,
 * category, status, scan info) and exposes filter/export hooks. When
 * online checkout lands and Order/Attendee models exist, the page
 * widens transparently.
 *
 * Filters: ?event=slug, ?status=scanned|voided|unscanned, ?q=ticket-number.
 */
class AttendeeController extends Controller
{
    public function index(Request $request, string $current_organization): Response
    {
        abort_unless($request->user()->can('attendee.view'), 403);

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        $eventSlug = (string) $request->query('event', '');
        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));

        $query = OfflineTicket::query()
            ->with(['event:id,slug,name,starts_at', 'category:id,name'])
            ->where('organisation_id', $org->id);

        if ($eventSlug !== '') {
            $query->whereHas('event', fn ($e) => $e->where('slug', $eventSlug));
        }
        if ($status === 'scanned') {
            $query->whereNotNull('scanned_at')->where('is_voided', false);
        } elseif ($status === 'voided') {
            $query->where('is_voided', true);
        } elseif ($status === 'unscanned') {
            $query->whereNull('scanned_at')->where('is_voided', false);
        }
        if ($q !== '') {
            $query->where('ticket_number', 'like', '%'.$q.'%');
        }

        $paginator = $query->orderByDesc('id')->paginate(50)->withQueryString();
        $rows = collect($paginator->items())->map(fn (OfflineTicket $t) => [
            'id' => $t->id,
            'ticket_number' => $t->ticket_number,
            'event_slug' => $t->event?->slug,
            'event_name' => $t->event?->name,
            'category' => $t->category?->name,
            'admission' => (string) $t->admission_type?->value,
            'pass' => (string) $t->pass_type?->value,
            'scanned_at' => $t->scanned_at?->toIso8601String(),
            'is_voided' => (bool) $t->is_voided,
            'voided_at' => $t->voided_at?->toIso8601String(),
            'scan_count' => (int) $t->scan_count,
        ])->all();

        $eventOptions = Event::query()
            ->where('organisation_id', $org->id)
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->map(fn (Event $e) => ['value' => $e->slug, 'label' => $e->name])
            ->all();

        $totals = OfflineTicket::query()
            ->where('organisation_id', $org->id)
            ->selectRaw('
                COUNT(*) AS total,
                SUM(CASE WHEN scanned_at IS NOT NULL AND is_voided = 0 THEN 1 ELSE 0 END) AS scanned,
                SUM(CASE WHEN is_voided = 1 THEN 1 ELSE 0 END) AS voided,
                SUM(CASE WHEN scanned_at IS NULL AND is_voided = 0 THEN 1 ELSE 0 END) AS unscanned
            ')
            ->first();

        return Inertia::render('attendees/index', [
            'filters' => ['event' => $eventSlug, 'status' => $status, 'q' => $q],
            'event_options' => $eventOptions,
            'totals' => [
                'total' => (int) ($totals->total ?? 0),
                'scanned' => (int) ($totals->scanned ?? 0),
                'voided' => (int) ($totals->voided ?? 0),
                'unscanned' => (int) ($totals->unscanned ?? 0),
            ],
            'rows' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'permissions' => [
                'can_export' => $request->user()->can('attendee.export'),
                'can_contact' => $request->user()->can('attendee.contact'),
            ],
            'breadcrumbs' => [
                ['title' => 'Attendees', 'href' => "/{$current_organization}/attendees"],
            ],
        ]);
    }

    /**
     * Stream a CSV of the currently-filtered roster. Same filter
     * parameters as the index page so the export matches what the
     * user is looking at.
     */
    public function export(Request $request, string $current_organization): StreamedResponse
    {
        abort_unless($request->user()->can('attendee.export'), 403);

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        $eventSlug = (string) $request->query('event', '');
        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));

        $query = OfflineTicket::query()
            ->with(['event:id,slug,name,starts_at', 'category:id,name'])
            ->where('organisation_id', $org->id);

        if ($eventSlug !== '') {
            $query->whereHas('event', fn ($e) => $e->where('slug', $eventSlug));
        }
        if ($status === 'scanned') {
            $query->whereNotNull('scanned_at')->where('is_voided', false);
        } elseif ($status === 'voided') {
            $query->where('is_voided', true);
        } elseif ($status === 'unscanned') {
            $query->whereNull('scanned_at')->where('is_voided', false);
        }
        if ($q !== '') {
            $query->where('ticket_number', 'like', '%'.$q.'%');
        }

        $filename = 'attendees-'.$current_organization.'-'.now()->format('Ymd-His').'.csv';

        return response()->stream(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ticket_number', 'event', 'category', 'admission', 'pass', 'scanned_at', 'is_voided', 'voided_at']);

            $query->orderBy('id')->chunkById(500, function ($chunk) use ($out) {
                foreach ($chunk as $t) {
                    fputcsv($out, [
                        $t->ticket_number,
                        $t->event?->name,
                        $t->category?->name,
                        $t->admission_type?->value,
                        $t->pass_type?->value,
                        $t->scanned_at?->toIso8601String(),
                        $t->is_voided ? 'yes' : 'no',
                        $t->voided_at?->toIso8601String(),
                    ]);
                }
            });

            fclose($out);
        }, HttpResponse::HTTP_OK, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
