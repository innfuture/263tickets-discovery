<?php

declare(strict_types=1);

use App\Enums\ScannerCapability;
use App\Models\ScannerProfile;

it('exposes the canonical capability vocab', function () {
    expect(ScannerCapability::all())->toBe(['scan', 'verify', 'revoke', 'view_analytics'])
        ->and(ScannerCapability::defaults())->toBe(['scan', 'verify']);
});

it('ScannerProfile.has() correctly checks string-vs-enum capability', function () {
    $p = new ScannerProfile([
        'organisation_id' => 'org',
        'name' => 'x',
        'capabilities' => ['scan', 'view_analytics'],
        'max_scans_per_minute' => 60,
        'duplicate_window_seconds' => 10,
    ]);

    expect($p->has('scan'))->toBeTrue()
        ->and($p->has('view_analytics'))->toBeTrue()
        ->and($p->has(ScannerCapability::Revoke->value))->toBeFalse();
});

it('canScanEvent allows any event when the allowlist is empty', function () {
    $p = new ScannerProfile([
        'organisation_id' => 'org',
        'name' => 'x',
        'capabilities' => ['scan'],
        'allowed_event_ids' => [],
        'max_scans_per_minute' => 60,
        'duplicate_window_seconds' => 10,
    ]);

    expect($p->canScanEvent(99))->toBeTrue();
});

it('canScanEvent restricts to listed event ids when allowlist is non-empty', function () {
    $p = new ScannerProfile([
        'organisation_id' => 'org',
        'name' => 'x',
        'capabilities' => ['scan'],
        'allowed_event_ids' => [1, 2, 3],
        'max_scans_per_minute' => 60,
        'duplicate_window_seconds' => 10,
    ]);

    expect($p->canScanEvent(2))->toBeTrue()
        ->and($p->canScanEvent(99))->toBeFalse();
});
