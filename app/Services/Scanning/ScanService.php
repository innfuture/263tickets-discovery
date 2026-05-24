<?php

declare(strict_types=1);

namespace App\Services\Scanning;

use App\Enums\ScannerCapability;
use App\Events\TicketScanned;
use App\Models\OfflineTicket;
use App\Models\ScanEvent;
use App\Models\ScannerDevice;
use App\Services\Scanning\Data\BiometricAttestation;
use App\Services\Scanning\Data\ScanContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrator for the per-scan flow:
 *
 *   1. Resolve the payload to an OfflineTicket (qr_payload OR
 *      ticket_number); recover the parent event if the ticket exists.
 *   2. Run the FraudEngine over a ScanContext.
 *   3. If the verdict is allow / warn, mark the ticket scanned
 *      (increment scan_count, set scanned_at on first scan, stamp
 *      device_id).
 *   4. Persist a ScanEvent row carrying the full audit trail.
 *   5. Fire the TicketScanned domain event so listeners can broadcast
 *      / webhook out / fan analytics.
 *
 * The verify-only path (`mode = verify`) runs the same pipeline but
 * never mutates the OfflineTicket — useful for the mobile app's
 * "look up" mode and for the velocity rule's pre-flight check.
 */
class ScanService
{
    public function __construct(protected FraudEngine $fraud) {}

    public const MODE_SCAN = 'scan';

    public const MODE_VERIFY = 'verify';

    /**
     * @param  array{
     *     payload: string,
     *     client_lat?: ?float,
     *     client_lng?: ?float,
     *     client_at?: ?string,
     *     client_ip?: ?string,
     *     device_meta?: ?array<string, mixed>,
     *     mode?: string,
     *     biometric?: ?array<string, mixed>,
     * }  $input
     */
    public function execute(ScannerDevice $device, array $input): ScanEvent
    {
        $startedAt = microtime(true);
        $serverNow = CarbonImmutable::now();

        $profile = $device->profile;
        $mode = $input['mode'] ?? self::MODE_SCAN;
        $payload = trim((string) $input['payload']);

        $ticket = $this->resolveTicket($payload, $profile->organisation_id);
        $event = $ticket?->event ?: null;

        // Biometric attestation is optional. Built fully from caller
        // input so the rule can decline / soft-fail / pass via the
        // configured BiometricProvider.
        $biometric = BiometricAttestation::fromArray(
            $input['biometric'] ?? null,
            $ticket?->uuid,
        );

        $context = new ScanContext(
            device: $device,
            profile: $profile,
            payload: $payload,
            ticket: $ticket,
            event: $event,
            clientLat: isset($input['client_lat']) ? (float) $input['client_lat'] : null,
            clientLng: isset($input['client_lng']) ? (float) $input['client_lng'] : null,
            clientIp: $input['client_ip'] ?? null,
            serverAt: $serverNow,
            clientAt: ! empty($input['client_at']) ? CarbonImmutable::parse((string) $input['client_at']) : null,
            biometric: $biometric,
        );

        ['verdict' => $verdict, 'flags' => $flags] = $this->fraud->evaluate($context);

        $admitted = false;
        if ($mode === self::MODE_SCAN
            && ! $verdict->isDeny()
            && $ticket !== null
            && $profile->has(ScannerCapability::Scan->value)) {
            $this->markScanned($ticket, $device, $serverNow);
            $admitted = true;
        }

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        $scan = ScanEvent::create([
            'scanner_device_id' => $device->id,
            'scanner_profile_id' => $profile->id,
            'event_id' => $event?->id,
            'offline_ticket_id' => $ticket?->id,
            'payload' => $payload,
            'verdict' => $verdict->outcome,
            'reason_code' => $verdict->reasonCode,
            'fraud_flags' => $flags,
            'was_duplicate' => collect($flags)->pluck('rule')->contains('duplicate_scan'),
            'was_voided' => $ticket?->is_voided ?? false,
            'was_admitted' => $admitted,
            'client_lat' => $context->clientLat,
            'client_lng' => $context->clientLng,
            'client_ip' => $context->clientIp ?? request()?->ip(),
            'client_at' => $context->clientAt,
            'latency_ms' => $latencyMs,
            'device_meta' => $input['device_meta'] ?? null,
        ]);

        TicketScanned::dispatch($scan);

        return $scan;
    }

    /**
     * Look up a ticket by either QR payload or human ticket number.
     * Scoped to the scanner profile's organisation so a token from
     * one org can never resolve another org's tickets.
     */
    protected function resolveTicket(string $payload, string $orgUuid): ?OfflineTicket
    {
        if ($payload === '') {
            return null;
        }

        // The offline_tickets table uses `organisation_id` as a UUID
        // FK to teams.uuid — matches the rest of the platform schema.
        return OfflineTicket::query()
            ->with('event:id,slug,name,starts_at,ends_at,doors_open_at,latitude,longitude')
            ->where('organisation_id', $orgUuid)
            ->where(function ($q) use ($payload) {
                $q->where('qr_payload', $payload)->orWhere('ticket_number', $payload);
            })
            ->first();
    }

    protected function markScanned(OfflineTicket $ticket, ScannerDevice $device, CarbonImmutable $now): void
    {
        // Atomic increment + first-scan stamp inside a transaction so
        // two devices racing on the same ticket don't both think they
        // were the first to admit.
        DB::transaction(function () use ($ticket, $device, $now) {
            $fresh = OfflineTicket::query()->whereKey($ticket->id)->lockForUpdate()->first();
            if ($fresh === null) {
                return;
            }

            $fresh->scan_count = (int) $fresh->scan_count + 1;
            if ($fresh->scanned_at === null) {
                $fresh->scanned_at = $now;
            }
            $fresh->device_id = (string) ($device->hardware_id ?: $device->uuid);
            $fresh->save();
        });
    }
}
