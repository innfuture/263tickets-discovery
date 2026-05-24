<?php

declare(strict_types=1);

namespace App\Services\Scanning\Nfc;

use App\Models\NfcVerification;
use App\Models\OfflineTicket;
use App\Services\Scanning\Nfc\Contracts\NfcVerificationProvider;
use App\Services\Scanning\Nfc\Data\NfcDecodedTap;
use App\Services\Scanning\Nfc\Exceptions\NfcVerificationException;
use Illuminate\Support\Facades\Log;

/**
 * Apple VAS (Value Added Services) — Apple Wallet → reader handshake.
 * Reader presents a merchant identifier; phone responds with an
 * encrypted payload signed by the device's secure enclave + the
 * pass's private key.
 *
 * Three accepted inbound shapes (in priority order, cheapest to most
 * expensive):
 *
 *   1. `serial:<value>`     Reader SDK pre-decoded; payload IS the serial.
 *   2. `<alnum>`            Plain serial, no prefix. Same as #1 minus prefix.
 *   3. Base64-encoded JSON envelope from a reader SDK that does NOT
 *      pre-decode. The envelope MUST carry a pre-derived `symmetric_key`
 *      (the ECDH derivation has to happen against the merchant
 *      private key + Apple's ephemeral public key, which lives inside
 *      the reader SDK and varies by vendor). With the symmetric key
 *      in hand, we decrypt AES-256-GCM and read `serialNumber`.
 *
 * If the envelope lacks `symmetric_key`, we refuse — pretending to
 * derive it from the public key alone (without the ephemeral) would
 * not actually decrypt anything real. Reader-SDK integrators that
 * need a different derivation path should subclass and override
 * `decryptBlob` for their hardware.
 */
class AppleVasProvider implements NfcVerificationProvider
{
    public function __construct(
        protected ?string $merchantId,
        protected ?string $certPath,
        protected ?string $certPassphrase,
    ) {}

    public function identifier(): string
    {
        return NfcVerification::PROVIDER_APPLE_VAS;
    }

    public function decode(string $encodedPayload, array $context = []): NfcDecodedTap
    {
        if (! $this->configured()) {
            throw new NfcVerificationException(
                'not_configured',
                'Apple VAS credentials are not configured.',
            );
        }

        $payload = trim($encodedPayload);

        if (str_starts_with($payload, 'serial:')) {
            $serial = substr($payload, strlen('serial:'));
        } elseif (preg_match('/^[A-Za-z0-9-]+$/', $payload)) {
            $serial = $payload;
        } else {
            $serial = $this->decryptBlob($payload);
        }

        $ticket = OfflineTicket::query()->where('serial', $serial)->first();
        if (! $ticket) {
            throw new NfcVerificationException('ticket_not_found', "No ticket for serial {$serial}.");
        }

        return new NfcDecodedTap(
            ticketUuid: (string) $ticket->uuid,
            qrEquivalentPayload: (string) $ticket->qr_payload,
            provider: $this->identifier(),
            payloadHash: hash('sha256', $payload.'|'.($this->merchantId ?? '')),
            metadata: ['serial' => $serial, 'merchant_id' => $this->merchantId],
        );
    }

    protected function configured(): bool
    {
        return ! empty($this->merchantId) && ! empty($this->certPath);
    }

    /**
     * Decrypt an Apple VAS envelope. Expected JSON shape:
     *
     *   {
     *     "data": "<base64 IV(12B) || ciphertext || GCM tag(16B)>",
     *     "symmetric_key": "<base64 raw 32-byte AES key>"
     *   }
     *
     * The reader SDK is responsible for performing ECDH against the
     * merchant private key + Apple's ephemeral public key and passing
     * the derived symmetric key alongside the ciphertext. This split
     * keeps the vendor-specific KDF in the SDK where it belongs and
     * keeps us cleanly decoupled.
     */
    protected function decryptBlob(string $payload): string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false) {
            throw new NfcVerificationException(
                'encrypted_blob_malformed',
                'Apple VAS payload is not valid base64.',
            );
        }

        $envelope = json_decode($raw, true);
        if (! is_array($envelope) || ! isset($envelope['data'])) {
            throw new NfcVerificationException(
                'encrypted_blob_malformed',
                'Apple VAS envelope missing required `data` field.',
            );
        }

        if (! isset($envelope['symmetric_key'])) {
            Log::warning('apple_vas.envelope_missing_symmetric_key', [
                'merchant_id' => $this->merchantId,
                'note' => 'Reader SDK must perform ECDH and include the derived AES key in the envelope.',
            ]);

            throw new NfcVerificationException(
                'encrypted_blob_unsupported',
                'Apple VAS envelope lacks a pre-derived symmetric_key. Use a reader SDK that performs the ECDH derivation.',
            );
        }

        $pemSource = (string) file_get_contents((string) $this->certPath);
        $privateKey = openssl_pkey_get_private($pemSource, (string) ($this->certPassphrase ?? ''));
        if ($privateKey === false) {
            Log::warning('apple_vas.private_key_load_failed', ['cert_path' => $this->certPath]);
            throw new NfcVerificationException(
                'cert_load_failed',
                'Apple VAS merchant private key could not be loaded.',
            );
        }

        $cipherText = (string) base64_decode((string) $envelope['data'], true);
        if (strlen($cipherText) < 28) {
            throw new NfcVerificationException(
                'encrypted_blob_malformed',
                'Apple VAS ciphertext too short to contain IV + tag.',
            );
        }

        $iv = substr($cipherText, 0, 12);
        $tag = substr($cipherText, -16);
        $body = substr($cipherText, 12, -16);

        $symmetricKey = (string) base64_decode((string) $envelope['symmetric_key'], true);
        if (strlen($symmetricKey) !== 32) {
            throw new NfcVerificationException(
                'encrypted_blob_malformed',
                'Apple VAS symmetric_key must decode to 32 bytes (AES-256).',
            );
        }

        $plaintext = openssl_decrypt(
            $body,
            'aes-256-gcm',
            $symmetricKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($plaintext === false) {
            throw new NfcVerificationException(
                'encrypted_blob_decrypt_failed',
                'Apple VAS payload failed AES-GCM decryption.',
            );
        }

        $inner = json_decode($plaintext, true);
        $serial = is_array($inner) ? (string) ($inner['serialNumber'] ?? '') : '';
        if ($serial === '') {
            throw new NfcVerificationException(
                'encrypted_blob_no_serial',
                'Decrypted Apple VAS payload did not contain a serial number.',
            );
        }

        return $serial;
    }
}
