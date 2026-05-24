<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Scanning;

use App\Enums\ScannerCapability;
use App\Http\Controllers\Controller;
use App\Models\ScanEvent;
use App\Models\ScannerDevice;
use App\Services\Scanning\ScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Scan endpoints — single + batch.
 *
 * POST /api/v1/scanning/scan
 *   { "payload": "tkt_xyz123",
 *     "client_lat": -17.82, "client_lng": 31.05,
 *     "client_at": "2026-05-23T19:01:14Z",
 *     "device_meta": {"battery": 0.41, "signal": "4g"} }
 *
 *   200 { "verdict": "allow", "was_admitted": true, "ticket": {...}, "flags": [...] }
 *
 * POST /api/v1/scanning/scan/batch
 *   { "scans": [ {payload, client_at, …}, …up to 100… ] }
 *   200 { "results": [ {scan_uuid, verdict, ...}, ... ] }
 *
 * Batch is the offline-sync path: the scanner queues scans locally
 * during connectivity loss and pushes them when back online. Each
 * scan in the batch is processed independently — one bad payload
 * doesn't fail the batch.
 */
class ScanController extends Controller
{
    public function __construct(protected ScanService $service) {}

    public function single(Request $request): JsonResponse
    {
        $data = $this->validatedScan($request);

        /** @var ScannerDevice $device */
        $device = $request->attributes->get('scanner_device');

        $this->assertCapability($device, ScannerCapability::Scan->value);

        $scan = $this->service->execute($device, $data + ['mode' => ScanService::MODE_SCAN]);

        return response()->json($this->serialise($scan), $scan->verdict === 'deny' ? 409 : 200);
    }

    public function batch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scans' => ['required', 'array', 'min:1', 'max:100'],
            'scans.*.payload' => ['required', 'string', 'max:255'],
            'scans.*.client_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'scans.*.client_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'scans.*.client_at' => ['nullable', 'date'],
            'scans.*.device_meta' => ['nullable', 'array'],
        ]);

        /** @var ScannerDevice $device */
        $device = $request->attributes->get('scanner_device');
        $this->assertCapability($device, ScannerCapability::Scan->value);

        $results = [];
        foreach ($data['scans'] as $payload) {
            try {
                $scan = $this->service->execute($device, $payload + ['mode' => ScanService::MODE_SCAN]);
                $results[] = $this->serialise($scan);
            } catch (\Throwable $e) {
                $results[] = [
                    'verdict' => 'deny',
                    'reason_code' => 'processing_error',
                    'message' => $e->getMessage(),
                    'payload' => $payload['payload'] ?? null,
                ];
            }
        }

        return response()->json(['results' => $results]);
    }

    /**
     * @return array{payload: string, client_lat: ?float, client_lng: ?float, client_at: ?string, device_meta: ?array<string,mixed>, client_ip: ?string, biometric: ?array<string, mixed>}
     */
    protected function validatedScan(Request $request): array
    {
        $v = $request->validate([
            'payload' => ['required', 'string', 'max:255'],
            'client_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'client_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'client_at' => ['nullable', 'date'],
            'device_meta' => ['nullable', 'array'],
            // Optional biometric attestation produced by the on-device
            // matcher. Raw biometric NEVER leaves the device — only
            // confidence + signed nonce.
            'biometric' => ['nullable', 'array'],
            'biometric.algorithm' => ['required_with:biometric', 'string', 'max:60'],
            'biometric.confidence' => ['required_with:biometric', 'numeric', 'between:0,1'],
            'biometric.nonce' => ['required_with:biometric', 'string', 'max:120'],
            'biometric.signature' => ['required_with:biometric', 'string', 'max:255'],
        ]);

        return [
            'payload' => $v['payload'],
            'client_lat' => $v['client_lat'] ?? null,
            'client_lng' => $v['client_lng'] ?? null,
            'client_at' => $v['client_at'] ?? null,
            'device_meta' => $v['device_meta'] ?? null,
            'biometric' => $v['biometric'] ?? null,
            'client_ip' => $request->ip(),
        ];
    }

    protected function assertCapability(ScannerDevice $device, string $cap): void
    {
        abort_unless($device->profile->has($cap), 403, "Profile lacks capability: {$cap}");
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialise(ScanEvent $s): array
    {
        $ticket = $s->ticket;

        return [
            'scan_uuid' => $s->uuid,
            'verdict' => $s->verdict,
            'reason_code' => $s->reason_code,
            'was_admitted' => (bool) $s->was_admitted,
            'was_duplicate' => (bool) $s->was_duplicate,
            'was_voided' => (bool) $s->was_voided,
            'flags' => $s->fraud_flags ?? [],
            'latency_ms' => (int) $s->latency_ms,
            'ticket' => $ticket ? [
                'uuid' => $ticket->uuid,
                'ticket_number' => $ticket->ticket_number,
                'scan_count' => (int) $ticket->scan_count,
                'admission_type' => $ticket->admission_type?->value,
                'pass_type' => $ticket->pass_type?->value,
                'is_voided' => (bool) $ticket->is_voided,
            ] : null,
            'event' => $s->event ? [
                'id' => (int) $s->event->id,
                'slug' => $s->event->slug,
                'name' => $s->event->name,
            ] : null,
            'server_time' => $s->created_at?->toIso8601String(),
        ];
    }
}
