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
            // Plain serial, no prefix.
            $serial = $payload;
        } else {
            // Encrypted blob path — needs the merchant private key.
            // Log + reject for now; the integrator wires the decrypt.
            Log::warning('apple_vas.encrypted_blob_received', [
                'merchant_id' => $this->merchantId,
                'note' => 'wire openssl_decrypt with cert to handle this path',
            ]);

            throw new NfcVerificationException(
                'encrypted_blob_unsupported',
                'Encrypted Apple VAS blob received but decrypt path is not wired. Use a reader SDK that pre-decrypts to serial.',
            );
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
}
