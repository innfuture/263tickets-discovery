<?php

declare(strict_types=1);

namespace App\Services\Extensions;

/**
 * Canonical permission registry for extensions. Manifests declare
 * a subset; org admins consent to (potentially a further subset);
 * runtime checks against the granted list.
 *
 * Naming: `<resource>.<action>` — e.g. `events.read`, `orders.write`.
 * Wildcard `*` means "all" — only granted for fully-trusted dev tools.
 *
 * To add a permission: extend ALL + add a description in DESCRIPTIONS.
 * The marketplace UI surfaces the description verbatim when prompting
 * the org admin at install time.
 */
class ExtensionPermission
{
    public const EVENTS_READ = 'events.read';

    public const EVENTS_WRITE = 'events.write';

    public const ORDERS_READ = 'orders.read';

    public const ORDERS_WRITE = 'orders.write';

    public const ATTENDEES_READ = 'attendees.read';

    public const ATTENDEES_WRITE = 'attendees.write';

    public const REFUNDS_WRITE = 'refunds.write';

    public const SCANS_READ = 'scans.read';

    public const ANALYTICS_READ = 'analytics.read';

    public const MESSAGES_SEND = 'messages.send';

    public const WEBHOOKS_SUBSCRIBE = 'webhooks.subscribe';

    public const STOREFRONT_BRAND_WRITE = 'storefront.brand.write';

    public const ALL = [
        self::EVENTS_READ, self::EVENTS_WRITE,
        self::ORDERS_READ, self::ORDERS_WRITE,
        self::ATTENDEES_READ, self::ATTENDEES_WRITE,
        self::REFUNDS_WRITE,
        self::SCANS_READ,
        self::ANALYTICS_READ,
        self::MESSAGES_SEND,
        self::WEBHOOKS_SUBSCRIBE,
        self::STOREFRONT_BRAND_WRITE,
    ];

    public const DESCRIPTIONS = [
        self::EVENTS_READ => 'View your events, schedules, and venue info.',
        self::EVENTS_WRITE => 'Create or edit events on your behalf.',
        self::ORDERS_READ => 'View customer order details + buyer contact info.',
        self::ORDERS_WRITE => 'Issue comp tickets or manual orders on your behalf.',
        self::ATTENDEES_READ => 'See who has bought tickets to your events.',
        self::ATTENDEES_WRITE => 'Update attendee information on your behalf.',
        self::REFUNDS_WRITE => 'Approve or reject refund requests.',
        self::SCANS_READ => 'View gate-scan history + entry analytics.',
        self::ANALYTICS_READ => 'Read aggregated sales + attendance numbers.',
        self::MESSAGES_SEND => 'Send broadcast email/SMS to your buyers.',
        self::WEBHOOKS_SUBSCRIBE => 'Receive real-time event notifications.',
        self::STOREFRONT_BRAND_WRITE => 'Update your public storefront brand assets.',
    ];

    /**
     * Validate that every permission key in the manifest is known.
     *
     * @param  list<string>  $declared
     * @return list<string> the unknown keys (empty list = all valid)
     */
    public static function unknown(array $declared): array
    {
        return array_values(array_diff($declared, self::ALL));
    }
}
