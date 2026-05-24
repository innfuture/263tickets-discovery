<?php

declare(strict_types=1);

namespace App\Services\Scanning\Nfc\Data;

/**
 * Result of NfcVerificationProvider::decode(). Carries the underlying
 * ticket UUID + provider-specific metadata for audit.
 *
 * `qrEquivalentPayload` is what we hand to the existing scan pipeline
 * so all downstream code (FraudEngine, ScanService, OfflineTicket
 * lookup) treats an NFC tap exactly like a QR scan.
 */
final class NfcDecodedTap
{
    public function __construct(
        public readonly string $ticketUuid,
        public readonly string $qrEquivalentPayload,
        public readonly string $provider,
        public readonly string $payloadHash,
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {}
}
