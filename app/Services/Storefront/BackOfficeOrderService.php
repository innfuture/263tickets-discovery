<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Events\OrderPaid;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketCategory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Issues an Order without going through the buyer-facing checkout
 * flow — used by the back-office dashboard for comp tickets, manual
 * box-office sales, and the automation API for bulk grants.
 *
 * Skips the holds + payment dance, but reuses OrderFulfillment for
 * the OfflineTicket issuance + OrderPaid broadcast so the issued
 * tickets behave exactly like a normal purchase from the scanner's
 * point of view.
 */
class BackOfficeOrderService
{
    public function __construct(protected OrderFulfillment $fulfilment) {}

    /**
     * @param  array{
     *     buyer_name: string, buyer_email: string, payment_method: string,
     *     currency: string, items: list<array{ticket_category_id:int,
     *     quantity:int, unit_price_cents?:int|null,
     *     attendee_name?:string|null, attendee_email?:string|null}>,
     *     notes?: string|null
     * }  $input
     */
    public function issue(Event $event, array $input): Order
    {
        return DB::transaction(function () use ($event, $input) {
            $subtotal = 0;
            $attendeeData = [];

            foreach ($input['items'] as $row) {
                $tier = TicketCategory::query()->find($row['ticket_category_id']);
                if (! $tier || (int) $tier->event_id !== (int) $event->id) {
                    continue;
                }
                $unit = (int) ($row['unit_price_cents']
                    ?? ($input['payment_method'] === 'comp' ? 0 : (int) round((float) $tier->base_price * 100)));

                $subtotal += $unit * (int) $row['quantity'];
            }

            $order = Order::create([
                'organisation_id' => $event->organisation_id,
                'event_id' => $event->id,
                'buyer_name' => $input['buyer_name'],
                'buyer_email' => strtolower($input['buyer_email']),
                'status' => 'paid',
                'currency' => strtoupper($input['currency']),
                'subtotal_cents' => $subtotal,
                'discount_cents' => 0,
                'tax_cents' => 0,
                'fee_cents' => 0,
                'total_cents' => $subtotal,
                'payment_method' => $input['payment_method'],
                'placed_at' => Carbon::now(),
                'fulfilled_at' => Carbon::now(),
                'metadata' => array_filter([
                    'back_office' => true,
                    'notes' => $input['notes'] ?? null,
                ]),
            ]);

            // Borrow the same per-seat issuance loop OrderFulfillment uses,
            // but without a CheckoutSession — issue tickets inline.
            foreach ($input['items'] as $row) {
                $tier = TicketCategory::query()->find($row['ticket_category_id']);
                if (! $tier) {
                    continue;
                }
                $unit = (int) ($row['unit_price_cents']
                    ?? ($input['payment_method'] === 'comp' ? 0 : (int) round((float) $tier->base_price * 100)));

                for ($n = 0; $n < (int) $row['quantity']; $n++) {
                    $ticket = $this->fulfilment->issueOfflineTicketDirectly($event, $tier);

                    $order->items()->create([
                        'ticket_category_id' => $tier->id,
                        'offline_ticket_id' => $ticket->id,
                        'attendee_name' => $row['attendee_name'] ?? $input['buyer_name'],
                        'attendee_email' => $row['attendee_email'] ?? $input['buyer_email'],
                        'ticket_type' => (string) $tier->name,
                        'unit_price_cents' => $unit,
                        'currency' => strtoupper($input['currency']),
                        'qr_payload' => $ticket->qr_payload,
                    ]);
                }
            }

            $event->incrementTicketsSold((int) $order->items()->count());

            OrderPaid::dispatch($order);

            return $order->refresh();
        });
    }
}
