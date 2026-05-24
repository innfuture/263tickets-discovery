<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Canonical capabilities a scanner profile can hold. The mobile app
 * reads /api/v1/scanning/me to discover the live set; it must NOT
 * assume capabilities based on app version.
 */
enum ScannerCapability: string
{
    /** Mark a ticket scanned + admitted at the gate. */
    case Scan = 'scan';

    /** Look up a ticket's status without incrementing scan_count. */
    case Verify = 'verify';

    /** Void a ticket from the gate (e.g. counterfeit detected). */
    case Revoke = 'revoke';

    /** Read entry analytics + recent activity for the scanner's events. */
    case ViewAnalytics = 'view_analytics';

    /**
     * @return array<int, string>
     */
    public static function defaults(): array
    {
        return [self::Scan->value, self::Verify->value];
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
