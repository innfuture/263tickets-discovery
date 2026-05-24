<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Scanning;

use App\Enums\ScannerCapability;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\OfflineTicket;
use App\Models\ScanEvent;
use App\Models\ScannerDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/scanning/analytics/event/{event}
 *
 * Entry analytics scoped to one event the scanner can see. Returns:
 *   - issued vs scanned counters
 *   - per-minute scan velocity (last 60 minutes)
 *   - verdict breakdown (allow/warn/deny counts)
 *   - top reason codes for denials (last 24 hours)
 *
 * Mobile app uses this to render its "live event dashboard" tab.
 */
class AnalyticsController extends Controller
{
    public function eventAnalytics(Request $request, Event $event): JsonResponse
    {
        /** @var ScannerDevice $device */
        $device = $request->attributes->get('scanner_device');
        $profile = $device->profile;

        abort_unless($profile->has(ScannerCapability::ViewAnalytics->value), 403, 'Profile lacks analytics capability.');
        abort_unless($profile->canScanEvent((int) $event->id), 403, 'Event not in profile allowlist.');
        abort_unless($event->organisation_id === $profile->organisation_id, 404);

        $now = now();
        $hourAgo = $now->copy()->subHour();
        $dayAgo = $now->copy()->subDay();

        // Issued vs scanned counters.
        $tickets = OfflineTicket::query()
            ->where('event_id', $event->id)
            ->selectRaw('
                COUNT(*) AS issued,
                SUM(CASE WHEN scanned_at IS NOT NULL AND is_voided = 0 THEN 1 ELSE 0 END) AS scanned,
                SUM(CASE WHEN is_voided = 1 THEN 1 ELSE 0 END) AS voided
            ')
            ->first();

        // Per-minute velocity, last 60 minutes — useful for spotting
        // the door-opening spike.
        $velocity = ScanEvent::query()
            ->where('event_id', $event->id)
            ->where('was_admitted', true)
            ->where('created_at', '>=', $hourAgo)
            ->selectRaw('DATE_FORMAT(created_at, "%H:%i") AS m, COUNT(*) AS n')
            ->groupBy('m')
            ->orderBy('m')
            ->get()
            ->map(fn ($r) => ['minute' => (string) $r->m, 'admitted' => (int) $r->n])
            ->all();

        $verdicts = ScanEvent::query()
            ->where('event_id', $event->id)
            ->where('created_at', '>=', $dayAgo)
            ->selectRaw('verdict, COUNT(*) AS n')
            ->groupBy('verdict')
            ->pluck('n', 'verdict')
            ->all();

        $topDenials = ScanEvent::query()
            ->where('event_id', $event->id)
            ->where('verdict', 'deny')
            ->where('created_at', '>=', $dayAgo)
            ->whereNotNull('reason_code')
            ->selectRaw('reason_code, COUNT(*) AS n')
            ->groupBy('reason_code')
            ->orderByDesc('n')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['reason_code' => (string) $r->reason_code, 'count' => (int) $r->n])
            ->all();

        $issued = (int) ($tickets->issued ?? 0);
        $scanned = (int) ($tickets->scanned ?? 0);

        return response()->json([
            'event' => [
                'id' => (int) $event->id,
                'slug' => $event->slug,
                'name' => $event->name,
            ],
            'counters' => [
                'issued' => $issued,
                'scanned' => $scanned,
                'voided' => (int) ($tickets->voided ?? 0),
                'scan_rate_pct' => $issued > 0 ? round(($scanned / $issued) * 100, 1) : 0.0,
            ],
            'verdicts_24h' => [
                'allow' => (int) ($verdicts['allow'] ?? 0),
                'warn' => (int) ($verdicts['warn'] ?? 0),
                'deny' => (int) ($verdicts['deny'] ?? 0),
            ],
            'velocity_60m' => $velocity,
            'top_denials_24h' => $topDenials,
            'server_time' => $now->toIso8601String(),
        ]);
    }

    public function recentScans(Request $request): JsonResponse
    {
        /** @var ScannerDevice $device */
        $device = $request->attributes->get('scanner_device');

        $rows = ScanEvent::query()
            ->where('scanner_profile_id', $device->scanner_profile_id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (ScanEvent $s) => [
                'uuid' => $s->uuid,
                'payload' => $s->payload,
                'verdict' => $s->verdict,
                'reason_code' => $s->reason_code,
                'was_admitted' => (bool) $s->was_admitted,
                'event_id' => $s->event_id,
                'created_at' => $s->created_at?->toIso8601String(),
                'flags' => $s->fraud_flags ?? [],
            ])
            ->all();

        return response()->json(['scans' => $rows]);
    }
}
