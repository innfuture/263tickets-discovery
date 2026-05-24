<?php

declare(strict_types=1);

namespace App\Services\Developer;

use App\Models\DeveloperSubscription;

/**
 * Single source of truth for what each tier gets. Used by:
 *
 *   - TierGate middleware (feature checks)
 *   - rate limiter (per-tier quota)
 *   - DeveloperAccount key issuance (default scopes by tier)
 *
 *   Free        Public read-only catalog: events list + detail.
 *               100 req/hour. No webhooks.
 *   Basic       + organizer profile reads + aggregate order counts.
 *               1k req/hour. 1 webhook URL.
 *   Enterprise  + analytics, per-event sales rollups, scan history.
 *               20k req/hour. 5 webhook URLs.
 *   Premium     + custom rate limits, all event types, dedicated
 *               support. No engineering limits — limits negotiated.
 */
class DeveloperTierPolicy
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function definition(): array
    {
        return [
            DeveloperSubscription::TIER_FREE => [
                'monthly_quota' => 10_000,
                'requests_per_minute' => 60,
                'allowed_scopes' => ['events.read', 'events.list'],
                'webhook_subscriptions' => 0,
                'features' => ['catalog'],
            ],
            DeveloperSubscription::TIER_BASIC => [
                'monthly_quota' => 100_000,
                'requests_per_minute' => 120,
                'allowed_scopes' => ['events.read', 'events.list', 'orders.aggregate.read'],
                'webhook_subscriptions' => 1,
                'features' => ['catalog', 'aggregate_orders'],
            ],
            DeveloperSubscription::TIER_ENTERPRISE => [
                'monthly_quota' => 2_000_000,
                'requests_per_minute' => 1_000,
                'allowed_scopes' => [
                    'events.read', 'events.list',
                    'orders.aggregate.read',
                    'analytics.read',
                    'webhooks.subscribe',
                ],
                'webhook_subscriptions' => 5,
                'features' => ['catalog', 'aggregate_orders', 'analytics', 'webhooks'],
            ],
            DeveloperSubscription::TIER_PREMIUM => [
                'monthly_quota' => null, // negotiated
                'requests_per_minute' => 5_000,
                'allowed_scopes' => ['*'],
                'webhook_subscriptions' => 100,
                'features' => ['catalog', 'aggregate_orders', 'analytics', 'webhooks', 'priority_support', 'sla'],
            ],
        ];
    }

    public static function for(string $tier): array
    {
        return self::definition()[$tier] ?? self::definition()[DeveloperSubscription::TIER_FREE];
    }

    public static function tierAllows(string $tier, string $feature): bool
    {
        return in_array($feature, self::for($tier)['features'] ?? [], true);
    }

    public static function tierAllowsScope(string $tier, string $scope): bool
    {
        $allowed = self::for($tier)['allowed_scopes'] ?? [];

        return in_array('*', $allowed, true) || in_array($scope, $allowed, true);
    }
}
