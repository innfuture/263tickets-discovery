<?php

declare(strict_types=1);

namespace App\Services\Storefront\Privacy;

use App\Models\CheckoutSession;
use App\Models\Order;
use App\Models\WaitlistEntry;

/**
 * GDPR / "Subject Access Request" implementation. Pulls every record
 * the platform holds for a given email address into a single export
 * payload. The actual mailing of the export bundle is the caller's
 * responsibility — this just builds the payload.
 *
 * Stay deliberately narrow: only return rows where the email is the
 * subject (buyer_email / contact_email / attendee_email). Don't leak
 * unrelated data that happens to be on the same order.
 */
class BuyerDataExporter
{
    /** @return array<string, mixed> */
    public function export(string $email): array
    {
        $email = strtolower(trim($email));

        $orders = Order::query()
            ->where('buyer_email', $email)
            ->with('items')
            ->get()
            ->map(fn (Order $o) => [
                'reference' => $o->reference,
                'placed_at' => optional($o->placed_at)->toIso8601String(),
                'total_cents' => (int) $o->total_cents,
                'currency' => $o->currency,
                'buyer' => [
                    'name' => $o->buyer_name,
                    'phone' => $o->buyer_phone,
                    'country_code' => $o->buyer_country_code,
                ],
                'items' => $o->items->map(fn ($item) => [
                    'ticket_type' => $item->ticket_type,
                    'attendee_name' => $item->attendee_name,
                    'attendee_email' => $item->attendee_email,
                ])->all(),
            ]);

        $sessions = CheckoutSession::query()
            ->where('buyer_email', $email)
            ->get(['uuid', 'status', 'expires_at', 'created_at']);

        $waitlist = WaitlistEntry::query()
            ->where('email', $email)
            ->get(['uuid', 'event_id', 'status', 'created_at']);

        return [
            'subject_email' => $email,
            'generated_at' => now()->toIso8601String(),
            'orders' => $orders,
            'checkout_sessions' => $sessions,
            'waitlist_entries' => $waitlist,
        ];
    }
}
