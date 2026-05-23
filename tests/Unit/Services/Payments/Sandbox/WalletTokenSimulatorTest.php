<?php

declare(strict_types=1);

use App\Services\Payments\Sandbox\WalletTokenSimulator;

it('decodes Apple Pay paymentData into a synthetic DPAN with liability shift', function () {
    $sim = new WalletTokenSimulator;

    $out = $sim->decode('apple_pay', ['paymentData' => base64_encode('opaque-blob-123')]);

    expect($out['provider'])->toBe('apple_pay')
        ->and($out['network_token'])->toStartWith('aptok_')
        ->and($out['eci'])->toBe('5')
        ->and($out['liability_shift'])->toBeTrue()
        ->and($out['dpan_last4'])->toHaveLength(4);
});

it('Apple Pay decoding is deterministic for the same input', function () {
    $sim = new WalletTokenSimulator;

    $a = $sim->decode('apple_pay', ['paymentData' => 'fixed-blob']);
    $b = $sim->decode('apple_pay', ['paymentData' => 'fixed-blob']);

    expect($a)->toEqual($b);
});

it('Google Pay PAN_ONLY does not shift liability; CRYPTOGRAM_3DS does', function () {
    $sim = new WalletTokenSimulator;

    $panOnly = $sim->decode('google_pay', ['auth_method' => 'PAN_ONLY', 'token' => 'tk1']);
    $crypto = $sim->decode('google_pay', ['auth_method' => 'CRYPTOGRAM_3DS', 'token' => 'tk1']);

    expect($panOnly['liability_shift'])->toBeFalse()
        ->and($panOnly['eci'])->toBe('7')
        ->and($crypto['liability_shift'])->toBeTrue()
        ->and($crypto['eci'])->toBe('5');
});

it('rejects Apple Pay payloads missing paymentData', function () {
    $sim = new WalletTokenSimulator;

    expect(fn () => $sim->decode('apple_pay', []))->toThrow(InvalidArgumentException::class);
});

it('rejects unknown wallet providers', function () {
    expect(fn () => (new WalletTokenSimulator)->decode('paypal', ['token' => 'x']))
        ->toThrow(InvalidArgumentException::class);
});
