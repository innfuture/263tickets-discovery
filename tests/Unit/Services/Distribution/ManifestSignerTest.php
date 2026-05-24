<?php

declare(strict_types=1);

use App\Services\Distribution\ManifestSigner;

it('returns unsigned when the secret is blank', function () {
    $signer = new ManifestSigner('');
    expect($signer->sign('root', 'recipient'))->toBe('unsigned')
        ->and($signer->verify('unsigned', 'root', 'recipient'))->toBeFalse();
});

it('verifies its own signature', function () {
    $signer = new ManifestSigner('test-secret');
    $sig = $signer->sign('root123', 'recipient-uuid', 42);

    expect($signer->verify($sig, 'root123', 'recipient-uuid', 42))->toBeTrue();
});

it('rejects when the root differs', function () {
    $signer = new ManifestSigner('test-secret');
    $sig = $signer->sign('root123', 'recipient-uuid');

    expect($signer->verify($sig, 'differentroot', 'recipient-uuid'))->toBeFalse();
});

it('rejects when the recipient differs', function () {
    $signer = new ManifestSigner('test-secret');
    $sig = $signer->sign('root123', 'a');

    expect($signer->verify($sig, 'root123', 'b'))->toBeFalse();
});

it('rejects when the event scope differs', function () {
    $signer = new ManifestSigner('test-secret');
    $sig = $signer->sign('root123', 'r', 5);

    expect($signer->verify($sig, 'root123', 'r', 6))->toBeFalse();
});

it('rejects when the timestamp is outside the tolerance window', function () {
    $signer = new ManifestSigner('test-secret');
    // Sign 30 days ago.
    $sig = $signer->sign('root123', 'r', null, time() - 2592000);

    expect($signer->verify($sig, 'root123', 'r', null, toleranceSeconds: 86400))->toBeFalse();
});

it('rejects when the secret rotates', function () {
    $original = new ManifestSigner('old-secret');
    $sig = $original->sign('root123', 'r');
    $rotated = new ManifestSigner('new-secret');

    expect($rotated->verify($sig, 'root123', 'r'))->toBeFalse();
});

it('rejects a malformed signature', function () {
    $signer = new ManifestSigner('test-secret');
    expect($signer->verify('nonsense', 'root123', 'r'))->toBeFalse()
        ->and($signer->verify('t=,v1=', 'root123', 'r'))->toBeFalse();
});

it('produces a key id derived from the secret', function () {
    $signer = new ManifestSigner('test-secret');
    expect($signer->keyId())->toStartWith('kh-')
        ->and(strlen($signer->keyId()))->toBe(15); // kh- + 12 hex chars
});
