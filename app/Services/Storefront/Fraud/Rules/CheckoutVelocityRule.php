<?php

declare(strict_types=1);

namespace App\Services\Storefront\Fraud\Rules;

use App\Models\CheckoutSession;
use App\Models\Order;
use App\Services\Storefront\Fraud\Contracts\CheckoutFraudRule;
use App\Services\Storefront\Fraud\Data\CheckoutFraudVerdict;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Denies / warns when the same IP or buyer email tries to pay for
 * too many orders in a short window. Stolen-card testing fingerprint
 * — a single attacker walks a card list against many low-value
 * tickets. Default window: 5 paid orders in 10 minutes.
 */
class CheckoutVelocityRule implements CheckoutFraudRule
{
    public function evaluate(CheckoutSession $session, Request $request): CheckoutFraudVerdict
    {
        $window = (int) config('storefront.fraud.velocity_window_minutes', 10);
        $threshold = (int) config('storefront.fraud.velocity_threshold', 5);

        if ($threshold <= 0) {
            return CheckoutFraudVerdict::allow();
        }

        $since = Carbon::now()->subMinutes($window);

        $emailCount = Order::query()
            ->where('buyer_email', strtolower((string) $session->buyer_email))
            ->where('created_at', '>=', $since)
            ->count();

        $ipCount = Order::query()
            ->where('ip_address', (string) $session->ip_address)
            ->where('created_at', '>=', $since)
            ->count();

        $worst = max($emailCount, $ipCount);

        if ($worst >= $threshold * 2) {
            return CheckoutFraudVerdict::deny('checkout_velocity_exceeded', 5, [
                'email_orders' => $emailCount,
                'ip_orders' => $ipCount,
                'window_minutes' => $window,
            ]);
        }

        if ($worst >= $threshold) {
            return CheckoutFraudVerdict::warn('checkout_velocity_high', 3, [
                'email_orders' => $emailCount,
                'ip_orders' => $ipCount,
            ]);
        }

        return CheckoutFraudVerdict::allow();
    }
}
