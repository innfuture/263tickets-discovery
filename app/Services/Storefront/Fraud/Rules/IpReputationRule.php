<?php

declare(strict_types=1);

namespace App\Services\Storefront\Fraud\Rules;

use App\Models\CheckoutSession;
use App\Services\Storefront\Fraud\Contracts\CheckoutFraudRule;
use App\Services\Storefront\Fraud\Data\CheckoutFraudVerdict;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * IP reputation via an external service. Default integration is
 * `proxycheck.io` (free tier, no signup) — set
 * `STOREFRONT_PROXYCHECK_KEY` to use the paid tier or replace the
 * URL via `storefront.fraud.ip_reputation_url`.
 *
 * Result is cached per-IP for 1 hour so a busy gate doesn't burn
 * the rate limit.
 */
class IpReputationRule implements CheckoutFraudRule
{
    public function evaluate(CheckoutSession $session, Request $request): CheckoutFraudVerdict
    {
        $ip = (string) ($session->ip_address ?? $request->ip());
        if ($ip === '' || $ip === '127.0.0.1') {
            return CheckoutFraudVerdict::allow();
        }

        $signals = Cache::remember(
            'storefront.fraud.iprep.'.$ip,
            now()->addHour(),
            fn () => $this->lookup($ip),
        );

        if (! is_array($signals)) {
            return CheckoutFraudVerdict::allow();
        }

        if (! empty($signals['is_proxy']) || ! empty($signals['is_vpn'])) {
            return CheckoutFraudVerdict::warn('proxy_or_vpn', 2, [
                'is_proxy' => (bool) ($signals['is_proxy'] ?? false),
                'is_vpn' => (bool) ($signals['is_vpn'] ?? false),
            ]);
        }

        if (! empty($signals['is_tor'])) {
            return CheckoutFraudVerdict::deny('tor_exit', 5, ['ip' => $ip]);
        }

        return CheckoutFraudVerdict::allow();
    }

    /** @return array<string, mixed>|null */
    protected function lookup(string $ip): ?array
    {
        $url = (string) config('storefront.fraud.ip_reputation_url', '');
        if ($url === '') {
            return null;
        }

        try {
            $response = Http::timeout(2)->get(str_replace('{ip}', $ip, $url), array_filter([
                'key' => env('STOREFRONT_PROXYCHECK_KEY'),
                'vpn' => 1,
                'asn' => 1,
            ]));

            if (! $response->successful()) {
                return null;
            }

            $body = $response->json();
            $row = is_array($body) ? ($body[$ip] ?? null) : null;
            if (! is_array($row)) {
                return null;
            }

            return [
                'is_proxy' => ($row['proxy'] ?? '') === 'yes',
                'is_vpn' => ($row['type'] ?? '') === 'VPN',
                'is_tor' => ($row['type'] ?? '') === 'TOR',
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
