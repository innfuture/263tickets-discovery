<?php

declare(strict_types=1);

namespace App\Services\Distribution;

use App\Events\TicketActivated;
use App\Models\DistributionSale;
use App\Models\Distributor;
use App\Models\DistributorDevice;
use App\Models\OfflineTicket;
use App\Models\TicketCustodyLedgerEntry;
use App\Services\Distribution\Exceptions\SaleException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Records a point-of-sale ticket sale. Writes the operational
 * `distribution_sales` row + appends `sold` and `activated` ledger
 * entries + fires TicketActivated so downstream listeners (edge KV
 * push, SMS receipt, snapshot refresh) can act asynchronously.
 *
 * Anti-fraud checks executed inline:
 *   - Device must be paired + active.
 *   - Ticket must be in `received` state (custody belongs here).
 *   - One-sale-per-ticket enforced by ticket_uuid unique index.
 *   - Velocity guard (configurable per minute per device).
 *   - Sale time-window if configured (only sellable N days before event).
 *
 * Geo-anomaly is non-blocking by default (configurable hard-block).
 */
class SalesRecorder
{
    public function __construct(
        protected TicketCustodyLedger $ledger,
        protected GeofenceChecker $geofence,
        protected SalesVelocityGuard $velocity,
    ) {}

    /**
     * @param  array{customer_phone?: string, customer_name?: string, customer_email?: string, gps_lat?: float, gps_lng?: float, signature?: string}  $context
     */
    public function record(
        Distributor $distributor,
        DistributorDevice $device,
        OfflineTicket $ticket,
        int $amountCents,
        string $currency,
        array $context = [],
    ): DistributionSale {
        if (! $distributor->isActive()) {
            throw new SaleException('distributor_inactive', "Distributor {$distributor->uuid} is {$distributor->status}.");
        }

        if (! $device->isActive() || $device->distributor_id !== $distributor->id) {
            throw new SaleException('device_invalid', "Device {$device->uuid} is not an active terminal for this distributor.");
        }

        $state = $this->ledger->currentState((string) $ticket->uuid);
        if ($state !== TicketCustodyLedgerEntry::EVENT_RECEIVED) {
            throw new SaleException(
                'ticket_state_invalid',
                "Ticket {$ticket->uuid} is in state {$state}; cannot sell.",
            );
        }

        // Velocity guard: trip BEFORE writing anything. The guard
        // records the attempt as a system ledger entry so the
        // distributor's score reflects it even if the sale didn't land.
        $this->velocity->assertWithinLimits($device);

        $now = CarbonImmutable::now();

        // Time-window guard.
        $windowDays = (int) config('distribution.sales.sale_window_days_before_event', 0);
        if ($windowDays > 0 && $ticket->event_id && $ticket->event?->starts_at) {
            $eventStart = CarbonImmutable::parse($ticket->event->starts_at);
            $opensAt = $eventStart->subDays($windowDays);
            $closesAt = $ticket->event->ends_at
                ? CarbonImmutable::parse($ticket->event->ends_at)
                : $eventStart->addDay();
            if ($now->lessThan($opensAt) || $now->greaterThan($closesAt)) {
                throw new SaleException('outside_sale_window', "Ticket not sellable at {$now} (window {$opensAt}..{$closesAt}).");
            }
        }

        // Geo check — anomalies recorded but not blocking unless hard-blocked.
        $gpsLat = isset($context['gps_lat']) ? (float) $context['gps_lat'] : null;
        $gpsLng = isset($context['gps_lng']) ? (float) $context['gps_lng'] : null;
        $outsideFence = ($gpsLat !== null && $gpsLng !== null)
            ? ! $this->geofence->contains($distributor, $gpsLat, $gpsLng)
            : false;
        if ($outsideFence && (bool) config('distribution.sales.geofence_hard_block', false)) {
            throw new SaleException('outside_geofence', 'Sale outside distributor geofence (hard-block enabled).');
        }

        return DB::transaction(function () use ($distributor, $device, $ticket, $amountCents, $currency, $context, $now, $outsideFence): DistributionSale {
            // One sale per ticket — the unique index on ticket_uuid is
            // the load-bearing guarantee; this select-for-update
            // protects the read-then-write between this transaction
            // and other concurrent attempts.
            $existing = DistributionSale::query()
                ->where('ticket_uuid', $ticket->uuid)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                throw new SaleException('already_sold', "Ticket {$ticket->uuid} already has a sale (UUID {$existing->uuid}).");
            }

            $anomalyFlags = [];
            if ($outsideFence) {
                $anomalyFlags[] = 'outside_geofence';
            }

            $sale = DistributionSale::create([
                'distributor_id' => $distributor->id,
                'distributor_device_id' => $device->id,
                'offline_ticket_id' => $ticket->id,
                'ticket_uuid' => (string) $ticket->uuid,
                'event_id' => $ticket->event_id,
                'customer_phone' => $context['customer_phone'] ?? null,
                'customer_name' => $context['customer_name'] ?? null,
                'customer_email' => $context['customer_email'] ?? null,
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'gps_lat' => $context['gps_lat'] ?? null,
                'gps_lng' => $context['gps_lng'] ?? null,
                'outside_geofence' => $outsideFence,
                'flagged_anomaly' => $anomalyFlags !== [],
                'anomaly_flags' => $anomalyFlags !== [] ? $anomalyFlags : null,
                'sold_at' => $now,
            ]);

            // Ledger: sold → activated. Two entries because activation
            // is what flips the gate verdict; sold is the financial
            // record. Splitting them lets refunds void the activation
            // without rewriting the sale.
            $signature = (string) ($context['signature'] ?? '');
            $this->ledger->append(
                ticketUuid: (string) $ticket->uuid,
                eventType: TicketCustodyLedgerEntry::EVENT_SOLD,
                actorType: TicketCustodyLedgerEntry::ACTOR_DEVICE,
                actorId: $device->id,
                payload: [
                    'sale_uuid' => $sale->uuid,
                    'distributor_uuid' => $distributor->uuid,
                    'amount_cents' => $amountCents,
                    'currency' => $currency,
                    'customer_phone_hash' => isset($context['customer_phone'])
                        ? hash('sha256', (string) $context['customer_phone'])
                        : null,
                    'outside_geofence' => $outsideFence,
                ],
                signature: $signature !== '' ? $signature : null,
                signingKeyId: 'device-'.$device->uuid,
                occurredAt: $now,
            );

            $this->ledger->append(
                ticketUuid: (string) $ticket->uuid,
                eventType: TicketCustodyLedgerEntry::EVENT_ACTIVATED,
                actorType: TicketCustodyLedgerEntry::ACTOR_SYSTEM,
                actorId: 0,
                payload: [
                    'sale_uuid' => $sale->uuid,
                    'reason' => 'sale_completed',
                ],
                occurredAt: $now,
            );

            if ($outsideFence) {
                $this->ledger->append(
                    ticketUuid: (string) $ticket->uuid,
                    eventType: TicketCustodyLedgerEntry::EVENT_GEO_ANOMALY,
                    actorType: TicketCustodyLedgerEntry::ACTOR_SYSTEM,
                    actorId: 0,
                    payload: [
                        'sale_uuid' => $sale->uuid,
                        'gps_lat' => $context['gps_lat'] ?? null,
                        'gps_lng' => $context['gps_lng'] ?? null,
                    ],
                    occurredAt: $now,
                );
            }

            Event::dispatch(new TicketActivated($ticket, $sale->uuid));

            return $sale;
        });
    }
}
