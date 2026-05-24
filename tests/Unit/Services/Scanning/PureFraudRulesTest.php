<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\OfflineTicket;
use App\Models\ScannerDevice;
use App\Models\ScannerProfile;
use App\Services\Scanning\Data\ScanContext;
use App\Services\Scanning\Rules\GeofenceRule;
use App\Services\Scanning\Rules\OffPeakRule;
use App\Services\Scanning\Rules\TicketNotFoundRule;
use App\Services\Scanning\Rules\VoidedTicketRule;
use App\Services\Scanning\Rules\WrongEventRule;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Pure rules — those that don't touch the database. The Velocity rule
 * + DuplicateScan rule both need DB access (ScanEvent::count + ticket
 * scanned_at) so they're covered in a separate feature test once the
 * test env has sqlite.
 */
function makeContext(?OfflineTicket $ticket = null, ?Event $event = null, array $opts = []): ScanContext
{
    $profile = new ScannerProfile([
        'organisation_id' => 'org-uuid',
        'name' => 'Main entrance',
        'capabilities' => ['scan', 'verify'],
        'allowed_event_ids' => $opts['allowed_event_ids'] ?? [],
        'max_scans_per_minute' => 60,
        'duplicate_window_seconds' => 10,
        'status' => 'active',
    ]);
    $profile->id = 1;

    $device = new ScannerDevice([
        'device_label' => 'Test device',
        'status' => 'active',
    ]);
    $device->id = 1;
    $device->setRelation('profile', $profile);

    return new ScanContext(
        device: $device,
        profile: $profile,
        payload: $opts['payload'] ?? 'TKT-001',
        ticket: $ticket,
        event: $event,
        clientLat: $opts['client_lat'] ?? null,
        clientLng: $opts['client_lng'] ?? null,
        clientIp: $opts['client_ip'] ?? null,
        serverAt: $opts['server_at'] ?? CarbonImmutable::parse('2026-05-23 19:30:00'),
        clientAt: null,
    );
}

/* ────────── TicketNotFoundRule ────────── */

it('TicketNotFound denies when no ticket resolved', function () {
    $verdict = (new TicketNotFoundRule)->evaluate(makeContext(ticket: null));
    expect($verdict->isDeny())->toBeTrue()
        ->and($verdict->reasonCode)->toBe('ticket_not_found');
});

it('TicketNotFound allows when a ticket is present', function () {
    $ticket = new OfflineTicket(['ticket_number' => 'X', 'is_voided' => false]);
    expect((new TicketNotFoundRule)->evaluate(makeContext(ticket: $ticket))->outcome)->toBe('allow');
});

/* ────────── VoidedTicketRule ────────── */

it('VoidedTicket denies voided tickets', function () {
    $ticket = new OfflineTicket(['is_voided' => true, 'void_reason' => 'lost']);
    $verdict = (new VoidedTicketRule)->evaluate(makeContext(ticket: $ticket));
    expect($verdict->isDeny())->toBeTrue()
        ->and($verdict->message)->toContain('lost');
});

it('VoidedTicket allows non-voided tickets', function () {
    $ticket = new OfflineTicket(['is_voided' => false]);
    expect((new VoidedTicketRule)->evaluate(makeContext(ticket: $ticket))->outcome)->toBe('allow');
});

it('VoidedTicket allows when no ticket is supplied', function () {
    expect((new VoidedTicketRule)->evaluate(makeContext(ticket: null))->outcome)->toBe('allow');
});

/* ────────── WrongEventRule ────────── */

it('WrongEvent denies a ticket whose event is outside the profile allowlist', function () {
    $event = new Event(['name' => 'Event B']);
    $event->id = 99;
    $ticket = new OfflineTicket(['is_voided' => false]);

    $ctx = makeContext(ticket: $ticket, event: $event, opts: ['allowed_event_ids' => [1, 2, 3]]);
    expect((new WrongEventRule)->evaluate($ctx)->isDeny())->toBeTrue();
});

it('WrongEvent allows when the profile allowlist is empty (all events)', function () {
    $event = new Event(['name' => 'X']);
    $event->id = 99;
    $ticket = new OfflineTicket(['is_voided' => false]);

    $ctx = makeContext(ticket: $ticket, event: $event, opts: ['allowed_event_ids' => []]);
    expect((new WrongEventRule)->evaluate($ctx)->outcome)->toBe('allow');
});

/* ────────── GeofenceRule ────────── */

it('Geofence is a no-op without GPS or venue coordinates', function () {
    $event = new Event(['latitude' => null, 'longitude' => null]);
    $ticket = new OfflineTicket;
    expect((new GeofenceRule)->evaluate(makeContext(ticket: $ticket, event: $event))->outcome)->toBe('allow');
});

it('Geofence warns when scan is far but within 10x radius', function () {
    config()->set('scanning.geofence_radius_km', 5.0);
    $event = new Event(['latitude' => -17.8252, 'longitude' => 31.0335]); // Harare
    $ticket = new OfflineTicket;

    // ~ 25 km away — > 5 but < 50.
    $ctx = makeContext(ticket: $ticket, event: $event, opts: ['client_lat' => -17.55, 'client_lng' => 31.10]);
    $v = (new GeofenceRule)->evaluate($ctx);

    expect($v->outcome)->toBe('warn')
        ->and($v->reasonCode)->toBe('far_from_venue');
});

it('Geofence denies when scan is more than 10x radius away', function () {
    config()->set('scanning.geofence_radius_km', 5.0);
    $event = new Event(['latitude' => -17.8252, 'longitude' => 31.0335]); // Harare
    $ticket = new OfflineTicket;

    // Cape Town — ~ 2300 km from Harare, well past 50 km.
    $ctx = makeContext(ticket: $ticket, event: $event, opts: ['client_lat' => -33.9249, 'client_lng' => 18.4241]);

    expect((new GeofenceRule)->evaluate($ctx)->isDeny())->toBeTrue();
});

/* ────────── OffPeakRule ────────── */

it('OffPeak allows scans during the event window', function () {
    config()->set('scanning.off_peak_grace_minutes', 60);
    $event = new Event([
        'starts_at' => CarbonImmutable::parse('2026-05-23 18:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-05-23 23:00:00'),
    ]);
    $ticket = new OfflineTicket;

    $ctx = makeContext(
        ticket: $ticket,
        event: $event,
        opts: ['server_at' => CarbonImmutable::parse('2026-05-23 19:30:00')],
    );

    expect((new OffPeakRule)->evaluate($ctx)->outcome)->toBe('allow');
});

it('OffPeak warns on scans well before doors open', function () {
    config()->set('scanning.off_peak_grace_minutes', 30);
    $event = new Event([
        'doors_open_at' => CarbonImmutable::parse('2026-05-23 18:00:00'),
        'starts_at' => CarbonImmutable::parse('2026-05-23 19:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-05-23 23:00:00'),
    ]);
    $ticket = new OfflineTicket;

    $ctx = makeContext(
        ticket: $ticket,
        event: $event,
        opts: ['server_at' => CarbonImmutable::parse('2026-05-23 14:00:00')],
    );

    expect((new OffPeakRule)->evaluate($ctx)->outcome)->toBe('warn');
});
