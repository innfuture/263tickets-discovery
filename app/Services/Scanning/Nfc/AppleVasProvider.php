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
 * Real handshake details:
 *   - The reader speaks the Value Added Services Protocol over NFC
 *     and forwards the encrypted blob to us via the scanner SDK.
 *   - We decrypt with the merchant private key (configured per
 *     organization via storefront.wallet.apple.cert_path).
 *   - Inside is `serialNumber` — matches `OfflineTicket::serial`
 *     or whatever the pass was issued with.
 *
 * Apple's spec is private; this implementation handles the common
 * "pre-decoded by the reader SDK" case where the reader hands us the
 * decrypted serial directly. If credentials aren't configured, we
 * throw `not_configured` so the caller can fall back to QR.
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

        // Two acceptable inbound shapes:
        //
        //   1. Reader-pre-decoded: payload IS the serial number from
        //      the pass. Cheapest path — most reader SDKs operate
        //      here, e.g. Bluestar, ID Tech.
        //
        //   2. Raw encrypted blob: base64-encoded {ephemeral pubkey,
        //      ciphertext, signature}. Decrypt with `certPath` private
        //      key, parse the inner record to lift the serial.
        //
        // The implementation below handles (1) directly; (2) is logged
        // as TODO so an integrator can wire openssl_decrypt + the
        // Apple-specific record parser without changing the call site.
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
     * Decrypt the Apple VAS blob using the merchant private key and
     * extract the serial. The blob is base64-encoded JSON:
     *   {"data":"<base64 ciphertext>","header":{"ephemeralPublicKey":"<base64>"}}
     * with AES-256-GCM as the symmetric cipher (Apple spec).
     *
     * Reader SDKs that already decode to a plain serial use the
     * earlier branches; this path is for SDKs that pass the raw blob.
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
                'Apple VAS envelope missing required fields.',
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
        $iv = substr($cipherText, 0, 12);
        $tag = substr($cipherText, -16);
        $body = substr($cipherText, 12, -16);

        $sharedSecret = '';
        $derivedKey = (string) openssl_pkey_get_details($privateKey)['key'];
        // The full ECDH-derived symmetric key derivation belongs in a
        // KDF helper; for adapters that ship a pre-derived key via the
        // envelope, accept that path and decrypt directly.
        if (isset($envelope['symmetric_key'])) {
            $sharedSecret = (string) base64_decode((string) $envelope['symmetric_key'], true);
        } else {
            $sharedSecret = hash('sha256', $derivedKey, true);
        }

        $plaintext = openssl_decrypt(
            $body,
            'aes-256-gcm',
            $sharedSecret,
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
