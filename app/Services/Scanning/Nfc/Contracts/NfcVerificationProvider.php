<?php

declare(strict_types=1);

namespace App\Services\Scanning\Nfc\Contracts;

use App\Services\Scanning\Nfc\Data\NfcDecodedTap;

/**
 * Decodes + verifies a single NFC tap payload from a mobile wallet.
 * Implementations:
 *
 *   AppleVasProvider          Apple VAS (Value Added Services) — used
 *                              by Apple Wallet event tickets.
 *   GoogleSmartTapProvider    Google Smart Tap — used by Google Wallet.
 *   StubNfcProvider           Dev / sandbox: accepts a plaintext
 *                              `<uuid>.<hmac>` payload identical to
 *                              the QR scheme — no NFC certs required.
 *
 * The encoded payload is what the reader hands us; we hand back a
 * decoded NfcDecodedTap (with the underlying ticket UUID) or throw
 * for invalid signatures / unknown formats.
 */
interface NfcVerificationProvider
{
    public function identifier(): string;

    /**
     * @param  string  $encodedPayload  raw base64/hex blob from the reader
     * @param  array<string, mixed>  $context  reader-side context (device id,
     *                                          merchant id, signed nonce…)
     */
    public function decode(string $encodedPayload, array $context = []): NfcDecodedTap;
}
