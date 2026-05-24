<?php

declare(strict_types=1);

namespace App\Services\Scanning\Nfc;

use App\Models\NfcVerification;
use App\Services\Scanning\Nfc\Contracts\NfcVerificationProvider;
use App\Services\Scanning\Nfc\Data\NfcDecodedTap;
use App\Services\Scanning\Nfc\Exceptions\NfcVerificationException;

/**
 * Stub NFC provider — accepts a plaintext `<uuid>.<hmac>` payload
 * (identical to the QR payload OfflineTicket::qr_payload carries).
 *
 * Used in dev / sandbox runs so the scanner app can exercise the
 * NFC code path without a real wallet + reader. Production swaps to
 * AppleVasProvider / GoogleSmartTapProvider via config.
 */
class StubNfcProvider implements NfcVerificationProvider
{
    public function identifier(): string
    {
        return NfcVerification::PROVIDER_STUB;
    }

    public function decode(string $encodedPayload, array $context = []): NfcDecodedTap
    {
        $payload = trim($encodedPayload);
        $parts = explode('.', $payload, 2);
        if (count($parts) !== 2) {
            throw new NfcVerificationException(
                'malformed_payload',
                'NFC payload must be `<uuid>.<hmac>` for the stub provider.',
            );
        }

        [$uuid, $providedHmac] = $parts;
        $secret = (string) config('app.key');
        $expected = substr(hash_hmac('sha256', $uuid, $secret), 0, 24);

        if (! hash_equals($expected, $providedHmac)) {
            throw new NfcVerificationException(
                'invalid_signature',
                'NFC payload signature does not match.',
            );
        }

        return new NfcDecodedTap(
            ticketUuid: $uuid,
            qrEquivalentPayload: $payload,
            provider: $this->identifier(),
            payloadHash: hash('sha256', $payload),
        );
    }
}
