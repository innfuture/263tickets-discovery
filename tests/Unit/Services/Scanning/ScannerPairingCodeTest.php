<?php

declare(strict_types=1);

use App\Models\ScannerPairingCode;
use Tests\TestCase;

uses(TestCase::class);

it('generates an 8-char Crockford-style code from a 32-char alphabet', function () {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    for ($i = 0; $i < 50; $i++) {
        $code = ScannerPairingCode::generateCode();
        expect(strlen($code))->toBe(8);
        foreach (str_split($code) as $ch) {
            expect(str_contains($alphabet, $ch))->toBeTrue();
        }
    }
});

it('produces non-repeating codes across calls (collision-free in small sample)', function () {
    $codes = collect(range(1, 100))->map(fn () => ScannerPairingCode::generateCode());
    expect($codes->unique()->count())->toBe(100);
});

it('isUsable returns false when used_at is set even before expiry', function () {
    $c = new ScannerPairingCode(['code' => 'X', 'expires_at' => now()->addHour()]);
    $c->used_at = now();
    expect($c->isUsable())->toBeFalse();
});

it('isUsable returns false when expired even if unused', function () {
    $c = new ScannerPairingCode(['code' => 'X', 'expires_at' => now()->subSecond()]);
    expect($c->isUsable())->toBeFalse();
});

it('isUsable returns true when fresh + unused', function () {
    $c = new ScannerPairingCode(['code' => 'X', 'expires_at' => now()->addHour()]);
    expect($c->isUsable())->toBeTrue();
});
