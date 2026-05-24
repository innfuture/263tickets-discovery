<?php

declare(strict_types=1);

namespace App\Services\Scanning\Nfc;

use App\Models\NfcVerification;
use App\Models\OfflineTicket;
use App\Services\Scanning\Nfc\Contracts\NfcVerificationProvider;
use App\Services\Scanning\Nfc\Exceptions\NfcVerificationException;
use Illuminate\Contracts\Container\Container;

/**
 * Picks the right NfcVerificationProvider by `provider` and runs the
 * decode. Persists a NfcVerification row regardless of outcome —
 * giving us an audit trail of attempted taps + replay protection
 * via the unique `payload_hash`.
 *
 * Returns the decoded tap so the calling controller can hand off to
 * the existing ScanService for verdict + fraud-rule evaluation.
 */
class NfcVerificationService
{
    public function __construct(protected Container $container) {}

    public function verify(string $provider, string $encodedPayload, array $context = []): NfcVerification
    {
        $impl = $this->resolve($provider);

        try {
            $decoded = $impl->decode($encodedPayload, $context);
        } catch (NfcVerificationException $e) {
            // Persist the failure with a synthetic payload hash so we
            // can correlate repeat attempts; let the caller observe.
            $row = NfcVerification::create([
                'provider' => $provider,
                'payload_hash' => substr(hash('sha256', $encodedPayload.':'.uniqid('', true)), 0, 64),
                'scanner_device_id' => $context['scanner_device_id'] ?? null,
                'verdict' => 'deny',
                'reason_code' => $e->reasonCode,
                'metadata' => ['error' => $e->getMessage()],
                'verified_at' => now(),
            ]);
            throw $e;
        }

        // Dedup: same encrypted payload presented twice is a replay,
        // unless the previous attempt was an explicit failure.
        $existing = NfcVerification::query()
            ->where('payload_hash', $decoded->payloadHash)
            ->where('verdict', '!=', 'deny')
            ->first();
        if ($existing) {
            throw new NfcVerificationException('replay', 'NFC payload already redeemed.');
        }

        $ticket = OfflineTicket::query()->where('uuid', $decoded->ticketUuid)->first();

        return NfcVerification::create([
            'offline_ticket_id' => $ticket?->id,
            'provider' => $decoded->provider,
            'payload_hash' => $decoded->payloadHash,
            'scanner_device_id' => $context['scanner_device_id'] ?? null,
            'verdict' => 'allow',
            'metadata' => $decoded->metadata,
            'verified_at' => now(),
        ]);
    }

    protected function resolve(string $provider): NfcVerificationProvider
    {
        $map = (array) config('scanning.nfc_providers', []);
        $class = $map[$provider] ?? null;
        if (! is_string($class) || ! class_exists($class)) {
            throw new NfcVerificationException(
                'unknown_provider',
                "Unknown NFC provider: {$provider}",
            );
        }

        return $this->container->make($class);
    }
}
