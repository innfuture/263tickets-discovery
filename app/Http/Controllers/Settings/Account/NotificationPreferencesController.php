<?php

namespace App\Http\Controllers\Settings\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Per-user notification toggles. Backed by `users.notification_preferences`
 * (JSON, nullable). Defaults live on `User::notificationDefaults()` so
 * adding a new trigger doesn't break older rows.
 */
class NotificationPreferencesController extends Controller
{
    /**
     * Each key here is a server-side notification trigger; the value
     * is the human label shown on the toggle row, grouped by topic.
     *
     * Update this map (and `User::notificationDefaults`) when a new
     * trigger is added in the platform.
     *
     * @var array<int, array{group: string, items: array<int, array{key: string, label: string, hint: string}>}>
     */
    private const TRIGGERS = [
        [
            'group' => 'Sales &amp; refunds',
            'items' => [
                ['key' => 'sale.new', 'label' => 'New ticket sale', 'hint' => 'A real-time email each time a ticket is purchased. Off by default for high-volume orgs.'],
                ['key' => 'refund.processed', 'label' => 'Refund processed', 'hint' => 'Confirmation when a refund is successfully issued.'],
            ],
        ],
        [
            'group' => 'Inventory &amp; payouts',
            'items' => [
                ['key' => 'inventory.low', 'label' => 'Ticket inventory low', 'hint' => 'Triggered when a ticket category drops below 10% remaining.'],
                ['key' => 'payout.received', 'label' => 'Payout received', 'hint' => 'When a payout reaches your bank account.'],
            ],
        ],
        [
            'group' => 'Digests',
            'items' => [
                ['key' => 'digest.daily', 'label' => 'Daily digest', 'hint' => 'One email per day summarising sales, refunds, and key event activity. Off by default to reduce noise.'],
                ['key' => 'digest.weekly', 'label' => 'Weekly digest', 'hint' => 'A Monday recap of the previous week, plus the upcoming-events outlook.'],
            ],
        ],
    ];

    public function edit(Request $request): Response
    {
        return Inertia::render('settings/account/notifications', [
            'preferences' => $request->user()->effectiveNotificationPreferences(),
            'triggers' => self::TRIGGERS,
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Notifications', 'href' => '/settings/notifications'],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        // Validate against the known trigger keys so a renamed key on
        // the client doesn't write junk to the JSON column.
        $known = collect(self::TRIGGERS)
            ->flatMap(fn ($g) => $g['items'])
            ->pluck('key')
            ->all();

        $data = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*' => ['boolean'],
        ]);

        $preferences = array_intersect_key(
            $data['preferences'],
            array_flip($known),
        );

        /** @var User $user */
        $user = $request->user();
        $user->forceFill(['notification_preferences' => $preferences])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notification preferences saved.')]);

        return back();
    }
}
