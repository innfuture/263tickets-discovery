<?php

declare(strict_types=1);

namespace App\Services\Distribution;

/**
 * Signs and verifies dispatch manifests. Default backend is HMAC-
 * SHA256 against a per-organization secret — same scheme as the
 * scanner-edge HMAC, kept deliberately simple so it can be rotated
 * via env without a key-management migration.
 *
 * A future upgrade path: swap the implementation behind a `ManifestSigner`
 * contract to use an asymmetric scheme (Ed25519) with the public key
 * pushed to recipients during onboarding. The verify() surface is
 * already shaped for that.
 */
class ManifestSigner
{
    public function __construct(
        protected ?string $secret = null,
    ) {
        $this->secret ??= (string) config('distribution.manifest_signing_secret', '');
    }

    /**
     * Returns `t=<unix>,v1=<hex>` over the canonical concatenation
     * of merkle_root, recipient identifier, and event scope. Same
     * t=,v1= format as the storefront-edge signature so dashboards
     * can reuse the parser.
     */
    public function sign(string $merkleRoot, string $recipientUuid, ?int $eventId = null, ?int $timestamp = null): string
    {
        if ($this->secret === '') {
            return 'unsigned';
        }

        $timestamp ??= time();
        $message = $this->canonical($merkleRoot, $recipientUuid, $eventId, $timestamp);
        $hex = hash_hmac('sha256', $message, $this->secret);

        return "t={$timestamp},v1={$hex}";
    }

    public function verify(string $signature, string $merkleRoot, string $recipientUuid, ?int $eventId = null, int $toleranceSeconds = 86400): bool
    {
        if ($this->secret === '' || $signature === 'unsigned') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signature) as $kv) {
            $pair = explode('=', $kv, 2);
            $parts[trim($pair[0])] = trim($pair[1] ?? '');
        }

        $ts = $parts['t'] ?? null;
        $v1 = $parts['v1'] ?? null;
        if (! is_numeric($ts) || ! is_string($v1)) {
            return false;
        }

        if (abs(time() - (int) $ts) > $toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', $this->canonical($merkleRoot, $recipientUuid, $eventId, (int) $ts), $this->secret);

        return hash_equals($expected, $v1);
    }

    public function keyId(): string
    {
        if ($this->secret === '') {
            return 'unsigned';
        }

        // Short fingerprint of the secret so logs / dispatch rows can
        // record WHICH key was used without leaking the key itself.
        return 'kh-'.substr(hash('sha256', $this->secret), 0, 12);
    }

    protected function canonical(string $merkleRoot, string $recipientUuid, ?int $eventId, int $timestamp): string
    {
        return implode('|', [
            'manifest.v1',
            $merkleRoot,
            $recipientUuid,
            (string) ($eventId ?? 'none'),
            (string) $timestamp,
        ]);
    }
}
