<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Enums\WaitlistStatus;
use App\Models\Event;
use App\Models\TicketCategory;
use App\Models\WaitlistEntry;
use Illuminate\Support\Carbon;

/**
 * Lightweight waitlist. The actual notification job is fired by the
 * `released-capacity` listener (TODO: wire to release events) — this
 * class is just the data layer.
 *
 * Idempotent: enrolling the same email + tier twice updates the
 * existing row rather than creating duplicates.
 */
class WaitlistManager
{
    public function enroll(
        Event $event,
        string $email,
        ?string $name = null,
        ?string $phone = null,
        ?TicketCategory $category = null,
        int $quantityRequested = 1,
    ): WaitlistEntry {
        $existing = WaitlistEntry::query()
            ->where('event_id', $event->id)
            ->where('email', strtolower(trim($email)))
            ->where('ticket_category_id', $category?->id)
            ->first();

        if ($existing) {
            $existing->fill([
                'name' => $name ?: $existing->name,
                'phone' => $phone ?: $existing->phone,
                'quantity_requested' => max(1, $quantityRequested),
                'status' => WaitlistStatus::Pending,
            ])->save();

            return $existing;
        }

        return WaitlistEntry::create([
            'event_id' => $event->id,
            'ticket_category_id' => $category?->id,
            'email' => strtolower(trim($email)),
            'name' => $name,
            'phone' => $phone,
            'quantity_requested' => max(1, $quantityRequested),
            'status' => WaitlistStatus::Pending,
        ]);
    }

    /**
     * Mark an entry as notified — sets expires_at according to the
     * configured notification_window. The notification dispatch itself
     * (email/SMS) is the caller's responsibility.
     */
    public function markNotified(WaitlistEntry $entry): WaitlistEntry
    {
        $window = (int) config('storefront.waitlist.notification_window_minutes', 30);

        $entry->forceFill([
            'status' => WaitlistStatus::Notified,
            'notified_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addMinutes($window),
        ])->save();

        return $entry->refresh();
    }
}
