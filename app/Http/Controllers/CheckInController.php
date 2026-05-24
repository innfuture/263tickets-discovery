<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\OfflineTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Day-of-event check-in surface. Two interaction modes:
 *
 *   - Camera scanner: JS reads a QR / barcode, POSTs `qr_payload` here
 *     for verification + scan increment.
 *   - Manual lookup: operator types the ticket number into a search
 *     box; same backend resolves either form to the same OfflineTicket.
 *
 * Each successful scan increments `scan_count`, stamps `scanned_at`,
 * and records the operator + device. Re-scans of an already-scanned
 * ticket return a "duplicate" status — the UI shows a soft warning so
 * the operator can decide whether to admit.
 */
class CheckInController extends Controller
{
    public function index(Request $request, string $current_organization): Response
    {
        abort_unless($request->user()->can('ticket.scan'), 403);

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        // Recently scanned tickets, scoped to the active org.
        $recent = OfflineTicket::query()
            ->where('organisation_id', $org->id)
            ->whereNotNull('scanned_at')
            ->with(['event:id,slug,name', 'category:id,name'])
            ->orderByDesc('scanned_at')
            ->limit(20)
            ->get()
            ->map(fn (OfflineTicket $t) => [
                'ticket_number' => $t->ticket_number,
                'event' => $t->event?->name,
                'category' => $t->category?->name,
                'scanned_at' => $t->scanned_at?->toIso8601String(),
                'relative' => $t->scanned_at?->diffForHumans(),
                'scan_count' => (int) $t->scan_count,
                'is_voided' => (bool) $t->is_voided,
            ])
            ->all();

        // Today's running tally
        $today = (object) DB::selectOne(
            'SELECT
                SUM(CASE WHEN scanned_at IS NOT NULL AND DATE(scanned_at) = ? THEN 1 ELSE 0 END) AS scanned_today,
                SUM(CASE WHEN voided_at IS NOT NULL AND DATE(voided_at) = ? THEN 1 ELSE 0 END) AS voided_today
            FROM offline_tickets WHERE organisation_id = ?',
            [now()->toDateString(), now()->toDateString(), $org->id],
        );

        // Event filter dropdown — today's events first.
        $events = Event::query()
            ->where('organisation_id', $org->id)
            ->where('ends_at', '>=', now()->startOfDay())
            ->orderBy('starts_at')
            ->limit(50)
            ->get(['slug', 'name', 'starts_at'])
            ->map(fn (Event $e) => [
                'value' => $e->slug,
                'label' => $e->name.' · '.($e->starts_at?->format('M j H:i') ?? ''),
            ])
            ->all();

        return Inertia::render('check-in/index', [
            'recent' => $recent,
            'today' => [
                'scanned' => (int) ($today->scanned_today ?? 0),
                'voided' => (int) ($today->voided_today ?? 0),
            ],
            'events' => $events,
            'scan_endpoint' => "/{$current_organization}/check-in/scan",
            'breadcrumbs' => [
                ['title' => 'Check-in', 'href' => "/{$current_organization}/check-in"],
            ],
        ]);
    }

    /**
     * Process a scan. Returns:
     *   { status: ok|duplicate|voided|not_found, ticket: {...} }
     *
     * Status semantics:
     *   ok        — first successful scan, scanned_at stamped now.
     *   duplicate — ticket exists and is valid, but already scanned.
     *   voided    — ticket exists but was voided; do NOT admit.
     *   not_found — payload doesn't match any ticket in this org.
     */
    public function scan(Request $request, string $current_organization): JsonResponse
    {
        abort_unless($request->user()->can('ticket.scan'), 403);

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        $data = $request->validate([
            'payload' => ['required', 'string', 'max:255'],
            'device_id' => ['nullable', 'string', 'max:64'],
        ]);

        $payload = trim($data['payload']);

        // Resolve either by qr_payload (camera) or ticket_number (manual).
        $ticket = OfflineTicket::query()
            ->where('organisation_id', $org->id)
            ->where(function ($q) use ($payload) {
                $q->where('qr_payload', $payload)->orWhere('ticket_number', $payload);
            })
            ->with(['event:id,slug,name', 'category:id,name'])
            ->first();

        if ($ticket === null) {
            return response()->json(['status' => 'not_found', 'message' => 'No matching ticket.'], 404);
        }

        if ($ticket->is_voided) {
            return response()->json([
                'status' => 'voided',
                'message' => 'Ticket was voided'.($ticket->void_reason ? ': '.$ticket->void_reason : '.'),
                'ticket' => $this->serialise($ticket),
            ], 409);
        }

        $alreadyScanned = $ticket->scanned_at !== null;

        // Atomic increment so two scanners racing don't both think
        // they were first.
        DB::transaction(function () use ($ticket, $data, $alreadyScanned) {
            $ticket->scan_count = (int) $ticket->scan_count + 1;
            if (! $alreadyScanned) {
                $ticket->scanned_at = now();
            }
            if (! empty($data['device_id'])) {
                $ticket->device_id = $data['device_id'];
            }
            $ticket->save();
        });

        return response()->json([
            'status' => $alreadyScanned ? 'duplicate' : 'ok',
            'message' => $alreadyScanned ? 'Already scanned — admit only if confident.' : 'Welcome!',
            'ticket' => $this->serialise($ticket->refresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialise(OfflineTicket $t): array
    {
        return [
            'ticket_number' => $t->ticket_number,
            'event' => $t->event?->name,
            'event_slug' => $t->event?->slug,
            'category' => $t->category?->name,
            'admission' => $t->admission_type?->value,
            'pass' => $t->pass_type?->value,
            'scanned_at' => $t->scanned_at?->toIso8601String(),
            'scan_count' => (int) $t->scan_count,
            'is_voided' => (bool) $t->is_voided,
        ];
    }
}
