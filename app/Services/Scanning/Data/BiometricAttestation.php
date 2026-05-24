<?php

declare(strict_types=1);

namespace App\Services\Scanning\Data;

/**
 * Wraps the scanner-supplied biometric verification result.
 *
 * Important: this DTO carries NO raw biometric. Only the device's
 * locally-computed confidence score + a signed attestation a server
 * provider can verify replay-safely via `nonce`.
 */
final class BiometricAttestation
{
    public function __construct(
        public readonly string $algorithm,        // e.g. "face_match.v1"
        public readonly float $confidence,        // [0, 1] from the device
        public readonly string $nonce,            // replay-protection id
        public readonly string $signature,        // device signature over (algorithm|confidence|nonce|ticket_uuid)
        public readonly ?string $ticketUuid = null,
    ) {}

    /**
     * @param  array<string, mixed>|null  $raw
     */
    public static function fromArray(?array $raw, ?string $ticketUuid): ?self
    {
        if ($raw === null) {
            return null;
        }
        $required = ['algorithm', 'confidence', 'nonce', 'signature'];
        foreach ($required as $k) {
            if (! array_key_exists($k, $raw)) {
                return null;
            }
        }

        return new self(
            algorithm: (string) $raw['algorithm'],
            confidence: (float) $raw['confidence'],
            nonce: (string) $raw['nonce'],
            signature: (string) $raw['signature'],
            ticketUuid: $ticketUuid,
        );
    }
}
