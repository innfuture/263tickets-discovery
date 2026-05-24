<?php

declare(strict_types=1);

use App\Models\OfflineTicket;
use App\Models\ScannerDevice;
use App\Models\ScannerProfile;
use App\Services\Scanning\Data\ScanContext;
use App\Services\Scanning\Rules\DuplicateScanRule;
use App\Services\Scanning\StubScanHistory;
use Carbon\CarbonImmutable;

function duplicateContext(?OfflineTicket $ticket, int $window = 10): ScanContext
{
    $profile = new ScannerProfile([
        'organisation_id' => 'org',
        'name' => 'P',
        'capabilities' => ['scan'],
        'max_scans_per_minute' => 60,
        'duplicate_window_seconds' => $window,
        'status' => 'active',
    ]);
    $profile->id = 1;

    $device = new ScannerDevice(['device_label' => 'd', 'status' => 'active']);
    $device->id = 1;
    $device->setRelation('profile', $profile);

    return new ScanContext(
        device: $device,
        profile: $profile,
        payload: 'X',
        ticket: $ticket,
        event: null,
        clientLat: null,
        clientLng: null,
        clientIp: null,
        serverAt: CarbonImmutable::parse('2026-05-23 19:00:00'),
        clientAt: null,
    );
}

it('allows when no ticket resolved', function () {
    $rule = new DuplicateScanRule(new StubScanHistory());
    expect($rule->evaluate(duplicateContext(null))->outcome)->toBe('allow');
});

it('allows when ticket has never been scanned (history returns null)', function () {
    $ticket = new OfflineTicket(['is_voided' => false]);
    $rule = new DuplicateScanRule(new StubScanHistory(lastScanAt: null));

    expect($rule->evaluate(duplicateContext($ticket))->outcome)->toBe('allow');
});

it('allows when profile window is 0 (duplicate detection disabled)', function () {
    $ticket = new OfflineTicket(['is_voided' => false]);
    $lastScan = CarbonImmutable::parse('2026-05-23 19:00:00')->subSeconds(2);
    $rule = new DuplicateScanRule(new StubScanHistory(lastScanAt: $lastScan));

    expect($rule->evaluate(duplicateContext($ticket, window: 0))->outcome)->toBe('allow');
});

it('warns with high severity when a scan happens inside the configured window', function () {
    $ticket = new OfflineTicket(['is_voided' => false]);
    // Server time = 19:00:00, last scan = 19:00:00 - 3s, window 10s.
    $lastScan = CarbonImmutable::parse('2026-05-23 19:00:00')->subSeconds(3);
    $rule = new DuplicateScanRule(new StubScanHistory(lastScanAt: $lastScan));

    $v = $rule->evaluate(duplicateContext($ticket, window: 10));

    expect($v->outcome)->toBe('warn')
        ->and($v->reasonCode)->toBe('duplicate_scan')
        ->and($v->severity)->toBe(4);
});

it('warns with low severity for re-scans outside the window', function () {
    $ticket = new OfflineTicket(['is_voided' => false]);
    $lastScan = CarbonImmutable::parse('2026-05-23 19:00:00')->subMinutes(30);
    $rule = new DuplicateScanRule(new StubScanHistory(lastScanAt: $lastScan));

    $v = $rule->evaluate(duplicateContext($ticket, window: 10));

    expect($v->outcome)->toBe('warn')
        ->and($v->severity)->toBe(2);
});
