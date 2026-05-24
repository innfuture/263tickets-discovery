<?php

declare(strict_types=1);

namespace App\Services\Scanning\Data;

use App\Models\Event;
use App\Models\OfflineTicket;
use App\Models\ScannerDevice;
use App\Models\ScannerProfile;
use Carbon\CarbonImmutable;

/**
 * Everything a FraudRule needs to make its call. Built fresh per scan
 * by the ScanController so the rules stay free of HTTP / DB concerns.
 *
 * `ticket` is null when the payload didn't match any ticket — rules
 * that want to flag scanner enumeration attempts read `ticket=null`.
 * `event` is null when the ticket is not yet resolved.
 *
 * `biometric` is null on routine scans. When an event requires
 * biometric re-verification (per `events.require_biometric_above`,
 * not yet a column — read from event.metadata.biometric_threshold for
 * now), the scanner attaches a `BiometricAttestation` and the
 * BiometricVerificationRule does the check.
 */
final class ScanContext
{
    public function __construct(
        public readonly ScannerDevice $device,
        public readonly ScannerProfile $profile,
        public readonly string $payload,
        public readonly ?OfflineTicket $ticket,
        public readonly ?Event $event,
        public readonly ?float $clientLat,
        public readonly ?float $clientLng,
        public readonly ?string $clientIp,
        public readonly CarbonImmutable $serverAt,
        public readonly ?CarbonImmutable $clientAt,
        public readonly ?BiometricAttestation $biometric = null,
    ) {}
}
