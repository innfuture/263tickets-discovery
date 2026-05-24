<?php

declare(strict_types=1);

namespace App\Jobs\Storefront;

use App\Enums\WaitlistStatus;
use App\Mail\WaitlistAvailableMail;
use App\Models\Event;
use App\Models\WaitlistEntry;
use App\Services\Storefront\TicketReservation;
use App\Services\Storefront\WaitlistManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Fires when inventory frees up — runs through the waitlist for that
 * event (or tier) and notifies as many pending entries as the freed
 * capacity allows. Each notified entry gets a `notification_window`
 * to convert before we move on to the next person.
 *
 * Notification side-channel: tries `WaitlistNotificationMail` if the
 * class exists, otherwise logs a structured event so QA can confirm
 * the chain ran end-to-end without a Mailable wired up yet.
 */
class NotifyWaitlistOnCapacityReleasedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $eventId) {}

    public function handle(TicketReservation $reservation, WaitlistManager $manager): void
    {
        $event = Event::query()->with('ticketCategories')->find($this->eventId);
        if (! $event) {
            return;
        }

        // Per-tier budget — only notify when there's actually inventory.
        $budgets = [];
        foreach ($event->ticketCategories as $tier) {
            $budgets[$tier->id] = $reservation->availabilityForCategory($tier);
        }

        $entries = WaitlistEntry::query()
            ->where('event_id', $event->id)
            ->where('status', WaitlistStatus::Pending->value)
            ->orderBy('id')
            ->get();

        foreach ($entries as $entry) {
            $tierId = (int) ($entry->ticket_category_id ?? 0);
            $available = $tierId > 0 ? ($budgets[$tierId] ?? null) : array_sum(array_filter($budgets));

            // null = unlimited tier — always notify.
            if ($available !== null && $available < $entry->quantity_requested) {
                continue;
            }

            $manager->markNotified($entry);
            $this->dispatchNotification($entry);

            if ($tierId > 0 && $available !== null) {
                $budgets[$tierId] = max(0, $available - $entry->quantity_requested);
            }
        }
    }

    protected function dispatchNotification(WaitlistEntry $entry): void
    {
        Mail::to($entry->email)->send(new WaitlistAvailableMail($entry));
    }
}
