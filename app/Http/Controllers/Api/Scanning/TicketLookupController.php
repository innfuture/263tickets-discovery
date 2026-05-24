<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Scanning;

use App\Enums\ScannerCapability;
use App\Http\Controllers\Controller;
use App\Models\ScannerDevice;
use App\Services\Scanning\ScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/scanning/tickets/{payload}
 *
 * Verify-only lookup. Runs the same FraudEngine pipeline so the
 * mobile app can preview what *would* happen if it submitted the
 * scan, without incrementing the ticket's scan_count.
 *
 * Useful for "VIP fast lane" UX where the operator wants to check
 * the ticket status before letting the holder through a non-scanned
 * door (e.g. handing a wristband to wear).
 */
class TicketLookupController extends Controller
{
    public function __construct(protected ScanService $service) {}

    public function __invoke(Request $request, string $payload): JsonResponse
    {
        /** @var ScannerDevice $device */
        $device = $request->attributes->get('scanner_device');
        abort_unless($device->profile->has(ScannerCapability::Verify->value), 403, 'Profile lacks verify capability.');

        $scan = $this->service->execute($device, [
            'payload' => $payload,
            'mode' => ScanService::MODE_VERIFY,
            'client_ip' => $request->ip(),
            'client_lat' => $request->header('X-Scanner-Lat') ? (float) $request->header('X-Scanner-Lat') : null,
            'client_lng' => $request->header('X-Scanner-Lng') ? (float) $request->header('X-Scanner-Lng') : null,
        ]);

        $ticket = $scan->ticket;

        return response()->json([
            'verdict' => $scan->verdict,
            'reason_code' => $scan->reason_code,
            'flags' => $scan->fraud_flags ?? [],
            'ticket' => $ticket ? [
                'uuid' => $ticket->uuid,
                'ticket_number' => $ticket->ticket_number,
                'scan_count' => (int) $ticket->scan_count,
                'is_voided' => (bool) $ticket->is_voided,
                'first_scanned_at' => $ticket->scanned_at?->toIso8601String(),
            ] : null,
            'event' => $scan->event ? [
                'id' => (int) $scan->event->id,
                'slug' => $scan->event->slug,
                'name' => $scan->event->name,
                'starts_at' => $scan->event->starts_at?->toIso8601String(),
            ] : null,
        ]);
    }
}
