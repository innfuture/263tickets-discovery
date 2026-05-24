<?php

declare(strict_types=1);

use App\Models\OfflineTicket;
use App\Models\ScannerDevice;
use App\Models\ScannerProfile;
use App\Services\Scanning\Data\ScanContext;
use App\Services\Scanning\Rules\VelocityRule;
use App\Services\Scanning\StubScanHistory;
use Carbon\CarbonImmutable;

function velocityContext(int $maxPerMinute = 60): ScanContext
{
    $profile = new ScannerProfile([
        'organisation_id' => 'org',
        'name' => 'P',
        'capabilities' => ['scan'],
        'max_scans_per_minute' => $maxPerMinute,
        'duplicate_window_seconds' => 10,
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
        ticket: new OfflineTicket(['is_voided' => false]),
        event: null,
        clientLat: null,
        clientLng: null,
        clientIp: null,
        serverAt: CarbonImmutable::parse('2026-05-23 19:00:00'),
        clientAt: null,
    );
}

it('allows when device is well under the limit', function () {
    $rule = new VelocityRule(new StubScanHistory(recentAdmittedCount: 5));
    expect($rule->evaluate(velocityContext(60))->outcome)->toBe('allow');
});

it('warns when device is at the limit but below 2x', function () {
    $rule = new VelocityRule(new StubScanHistory(recentAdmittedCount: 75));
    $v = $rule->evaluate(velocityContext(60));

    expect($v->outcome)->toBe('warn')
        ->and($v->reasonCode)->toBe('rate_high')
        ->and($v->message)->toContain('75 in last minute');
});

it('denies when device exceeds 2x the limit', function () {
    $rule = new VelocityRule(new StubScanHistory(recentAdmittedCount: 130));
    $v = $rule->evaluate(velocityContext(60));

    expect($v->outcome)->toBe('deny')
        ->and($v->reasonCode)->toBe('rate_exceeded')
        ->and($v->severity)->toBe(6);
});

it('is a no-op when profile has max_scans_per_minute <= 0', function () {
    $rule = new VelocityRule(new StubScanHistory(recentAdmittedCount: 9999));
    expect($rule->evaluate(velocityContext(0))->outcome)->toBe('allow');
});

it('boundary: exactly at limit warns; one below allows', function () {
    expect((new VelocityRule(new StubScanHistory(recentAdmittedCount: 60)))->evaluate(velocityContext(60))->outcome)
        ->toBe('warn');
    expect((new VelocityRule(new StubScanHistory(recentAdmittedCount: 59)))->evaluate(velocityContext(60))->outcome)
        ->toBe('allow');
});
