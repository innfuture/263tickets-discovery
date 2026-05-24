<?php

declare(strict_types=1);

use App\Services\Scanning\Nfc\AppleVasProvider;
use App\Services\Scanning\Nfc\Exceptions\NfcVerificationException;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Subclass that exposes decryptBlob for direct testing without going
 * through the OfflineTicket DB lookup that decode() performs at the
 * end. Validates the decrypt path itself, not the model resolution.
 */
class TestableAppleVasProvider extends AppleVasProvider
{
    public function decryptBlobPublic(string $payload): string
    {
        return $this->decryptBlob($payload);
    }
}

function makeAppleVasTempCert(): string
{
    $key = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
        'private_key_bits' => 2048,
    ]);
    openssl_pkey_export($key, $pem);
    $tmp = tempnam(sys_get_temp_dir(), 'avas-');
    file_put_contents($tmp, $pem);

    return $tmp;
}

function makeAppleVasEnvelope(string $plaintext, string $symmetricKey): string
{
    $iv = random_bytes(12);
    $ciphertext = openssl_encrypt(
        $plaintext,
        'aes-256-gcm',
        $symmetricKey,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
    );

    return base64_encode(json_encode([
        'data' => base64_encode($iv.$ciphertext.$tag),
        'symmetric_key' => base64_encode($symmetricKey),
    ]));
}

it('decrypts a well-formed Apple VAS envelope and lifts the serial', function () {
    $cert = makeAppleVasTempCert();
    $provider = new TestableAppleVasProvider('merchant.test', $cert, '');
    $key = random_bytes(32);
    $envelope = makeAppleVasEnvelope(json_encode(['serialNumber' => 'TKT-001']), $key);

    expect($provider->decryptBlobPublic($envelope))->toBe('TKT-001');

    @unlink($cert);
});

it('refuses an envelope without symmetric_key', function () {
    $cert = makeAppleVasTempCert();
    $provider = new TestableAppleVasProvider('merchant.test', $cert, '');
    $envelope = base64_encode(json_encode(['data' => base64_encode(str_repeat('x', 40))]));

    expect(fn () => $provider->decryptBlobPublic($envelope))
        ->toThrow(NfcVerificationException::class, 'symmetric_key');

    @unlink($cert);
});

it('rejects a non-base64 payload', function () {
    $cert = makeAppleVasTempCert();
    $provider = new TestableAppleVasProvider('merchant.test', $cert, '');

    expect(fn () => $provider->decryptBlobPublic("\xff\xff\xff not base64 \xff\xff"))
        ->toThrow(NfcVerificationException::class, 'base64');

    @unlink($cert);
});

it('rejects a symmetric_key of wrong length', function () {
    $cert = makeAppleVasTempCert();
    $provider = new TestableAppleVasProvider('merchant.test', $cert, '');
    $envelope = base64_encode(json_encode([
        'data' => base64_encode(str_repeat('x', 40)),
        'symmetric_key' => base64_encode(random_bytes(16)),
    ]));

    expect(fn () => $provider->decryptBlobPublic($envelope))
        ->toThrow(NfcVerificationException::class, '32 bytes');

    @unlink($cert);
});

it('rejects a ciphertext too short to contain IV + tag', function () {
    $cert = makeAppleVasTempCert();
    $provider = new TestableAppleVasProvider('merchant.test', $cert, '');
    $envelope = base64_encode(json_encode([
        'data' => base64_encode(str_repeat('x', 10)),
        'symmetric_key' => base64_encode(random_bytes(32)),
    ]));

    expect(fn () => $provider->decryptBlobPublic($envelope))
        ->toThrow(NfcVerificationException::class, 'IV');

    @unlink($cert);
});

it('rejects a decrypted payload without serialNumber', function () {
    $cert = makeAppleVasTempCert();
    $provider = new TestableAppleVasProvider('merchant.test', $cert, '');
    $key = random_bytes(32);
    $envelope = makeAppleVasEnvelope(json_encode(['somethingElse' => 'oops']), $key);

    expect(fn () => $provider->decryptBlobPublic($envelope))
        ->toThrow(NfcVerificationException::class, 'serial');

    @unlink($cert);
});
