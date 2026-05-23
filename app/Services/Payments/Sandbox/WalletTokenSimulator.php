<?php

declare(strict_types=1);

namespace App\Services\Payments\Sandbox;

/**
 * Synthetic Apple Pay / Google Pay payload acceptance (§5.2). We do not
 * implement real ECC decryption — the sandbox accepts any base64-shaped
 * blob and projects it onto a Stripe-style decrypted-token structure so
 * consumer code that branches on `eci_indicator` / `network_token`
 * keeps working.
 *
 * Two-tier behavior:
 *   apple_pay     payload presence required, returns DPAN + ECI 5
 *   google_pay    `auth_method` from input picks PAN_ONLY (no 3DS) vs
 *                 CRYPTOGRAM_3DS (auth-shifted, ECI 5)
 */
class WalletTokenSimulator
{
    /**
     * @param  array<string, mixed>  $walletPayload
     * @return array{provider:string, network_token:string, eci:string, dpan_last4:string, liability_shift:bool, auth_method:?string}
     */
    public function decode(string $provider, array $walletPayload): array
    {
        return match (strtolower($provider)) {
            'apple_pay', 'applepay' => $this->decodeApplePay($walletPayload),
            'google_pay', 'googlepay' => $this->decodeGooglePay($walletPayload),
            default => throw new \InvalidArgumentException("Unknown wallet provider: {$provider}"),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{provider:string, network_token:string, eci:string, dpan_last4:string, liability_shift:bool, auth_method:?string}
     */
    protected function decodeApplePay(array $payload): array
    {
        $opaque = (string) ($payload['paymentData'] ?? '');
        if ($opaque === '') {
            throw new \InvalidArgumentException('Apple Pay paymentData blob is required.');
        }

        // Deterministic synthesis: same opaque blob → same DPAN.
        $hash = hash('sha256', $opaque);

        return [
            'provider' => 'apple_pay',
            'network_token' => 'aptok_'.substr($hash, 0, 24),
            'eci' => '5',
            'dpan_last4' => substr($hash, -4),
            'liability_shift' => true,
            'auth_method' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{provider:string, network_token:string, eci:string, dpan_last4:string, liability_shift:bool, auth_method:?string}
     */
    protected function decodeGooglePay(array $payload): array
    {
        $method = (string) ($payload['auth_method'] ?? 'PAN_ONLY');
        $token = (string) ($payload['token'] ?? '');
        if ($token === '') {
            throw new \InvalidArgumentException('Google Pay token is required.');
        }

        $hash = hash('sha256', $token);
        $isCrypto = strcasecmp($method, 'CRYPTOGRAM_3DS') === 0;

        return [
            'provider' => 'google_pay',
            'network_token' => 'gptok_'.substr($hash, 0, 24),
            'eci' => $isCrypto ? '5' : '7',
            'dpan_last4' => substr($hash, -4),
            'liability_shift' => $isCrypto,
            'auth_method' => $method,
        ];
    }
}
