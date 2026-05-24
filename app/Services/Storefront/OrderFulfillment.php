<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Events\OrderPaid;
use App\Models\CheckoutSession;
use App\Models\Event;
use App\Models\OfflineTicket;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\TicketCategory;
use App\Models\TicketPromoCode;
use Illuminate\Container\Container;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Converts a completed checkout session into a permanent Order +
 * OrderItems + per-seat OfflineTicket rows (scannable). Invoked by:
 *
 *   - the payment webhook listener once the gateway settles
 *   - the dev "mark-paid" flow in the sandbox
 *
 * Idempotent: if the session already has an `order_id`, the existing
 * order is returned. Re-deliveries from a flaky gateway can't double-
 * issue tickets.
 *
 * Emits OrderPaid so listeners (confirmation email, analytics, the
 * organizer dashboard live counter) can react without coupling to
 * this class.
 */
class OrderFulfillment
{
    public function __construct(protected PriceCalculator $prices) {}

    /**
     * Resolve the addon manager lazily so existing tests that build
     * OrderFulfillment with just PriceCalculator keep working.
     */
    protected function addonManager(): AddonManager
    {
        return Container::getInstance()->make(AddonManager::class);
    }

    protected function giftCardManager(): GiftCardManager
    {
        return Container::getInstance()->make(GiftCardManager::class);
    }

    public function fulfill(CheckoutSession $session, ?string $paymentReference = null, ?string $paymentMethod = null): Order
    {
        if ($session->order_id) {
            return Order::query()->findOrFail($session->order_id);
        }

        $session->loadMissing('items.category', 'event', 'promoCode');

        // Recompute one last time so totals on the order are definitive.
        $quote = $this->prices->recompute($session);

        return DB::transaction(function () use ($session, $quote, $paymentReference, $paymentMethod) {
            // Pull the staged referral code so the listener can credit
            // the referrer without needing to inspect attendee_data
            // (which gets cleared by GDPR erasure).
            $referralCode = (string) (($session->attendee_data['_referral_code'] ?? '') ?: '');

            $order = Order::create([
                'organisation_id' => $session->organisation_id,
                'event_id' => $session->event_id,
                'checkout_session_id' => $session->id,
                'buyer_name' => $session->buyer_name ?: 'Guest',
                'buyer_email' => $session->buyer_email ?: '',
                'buyer_phone' => $session->buyer_phone,
                'buyer_country_code' => $session->buyer_country_code,
                'buyer_locale' => $session->buyer_locale,
                'status' => 'paid',
                'currency' => $session->currency,
                'subtotal_cents' => $quote->subtotalMinor,
                'discount_cents' => $quote->discountMinor,
                'tax_cents' => $quote->taxMinor,
                'fee_cents' => $quote->feeMinor,
                'total_cents' => $quote->totalMinor,
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentReference,
                'promo_code_used' => $session->promo_code_snapshot,
                'ip_address' => $session->ip_address,
                'utm_source' => $session->utm_source,
                'utm_medium' => $session->utm_medium,
                'utm_campaign' => $session->utm_campaign,
                'placed_at' => Carbon::now(),
                'fulfilled_at' => Carbon::now(),
                'metadata' => array_filter([
                    'price_lines' => array_map(fn ($l) => $l->toArray(), $quote->lines),
                    'referral_code' => $referralCode !== '' ? $referralCode : null,
                ]),
            ]);

            $attendeesByItem = $this->indexAttendees($session);

            // Seat-held inventory: claim holds first so seats and tier-
            // quantity issuance can't double-up on the same line.
            $seatsByTier = SeatHold::query()
                ->where('checkout_session_id', $session->id)
                ->whereNull('order_item_id')
                ->with('seat')
                ->get()
                ->groupBy(fn (SeatHold $h) => (int) ($h->seat?->ticket_category_id ?? 0));

            foreach ($session->items as $item) {
                $attendees = $attendeesByItem[$item->id] ?? [];
                $seatHolds = $seatsByTier->get((int) $item->ticket_category_id, collect());
                $isSeated = $seatHolds->isNotEmpty();

                // For seated items, issue exactly one ticket per held seat
                // (quantity already mirrors hold count, but trust the holds).
                $seatCount = $isSeated ? $seatHolds->count() : (int) $item->quantity;

                for ($n = 0; $n < $seatCount; $n++) {
                    $attendee = $attendees[$n] ?? [];
                    $hold = $isSeated ? $seatHolds->get($n) : null;

                    $ticket = $this->issueOfflineTicket($session, $item->category, $attendee);

                    $orderItem = OrderItem::create([
                        'order_id' => $order->id,
                        'ticket_category_id' => $item->ticket_category_id,
                        'offline_ticket_id' => $ticket->id,
                        'attendee_name' => $attendee['name'] ?? $session->buyer_name ?? 'Guest',
                        'attendee_email' => $attendee['email'] ?? $session->buyer_email,
                        'attendee_phone' => $attendee['phone'] ?? null,
                        'ticket_type' => (string) $item->category->name,
                        'unit_price_cents' => (int) $item->unit_price_cents,
                        'currency' => (string) $item->currency,
                        'qr_payload' => $ticket->qr_payload,
                    ]);

                    if ($hold) {
                        $hold->forceFill(['order_item_id' => $orderItem->id])->save();
                        Seat::query()->where('id', $hold->seat_id)
                            ->update(['status' => Seat::STATUS_SOLD]);
                    }
                }
            }

            if ($session->promo_code_id) {
                TicketPromoCode::query()
                    ->where('id', $session->promo_code_id)
                    ->increment('uses_count');
            }

            // Bundle / membership branches. Both stash an identifier
            // in attendee_data at session-create time; fulfilment fans
            // out to the matching service. Order rows themselves stay
            // simple — every issued OfflineTicket appears as an
            // OrderItem regardless of whether it came from a tier line
            // or a bundle line.
            $bundleSlug = (string) (($session->attendee_data['_bundle_slug'] ?? '') ?: '');
            if ($bundleSlug !== '') {
                $bundle = \App\Models\EventBundle::query()
                    ->where('slug', $bundleSlug)->first();
                if ($bundle) {
                    Container::getInstance()->make(BundleManager::class)
                        ->issueForOrder($order, $bundle);
                    $order->forceFill([
                        'metadata' => array_merge(
                            (array) $order->metadata,
                            ['bundle_id' => $bundle->id, 'bundle_slug' => $bundle->slug],
                        ),
                    ])->save();
                }
            }

            $membershipPlan = (array) ($session->attendee_data['_membership'] ?? []);
            if (! empty($membershipPlan['plan_name'])) {
                Container::getInstance()->make(MembershipManager::class)->startNew(
                    org: $session->organization,
                    planName: (string) $membershipPlan['plan_name'],
                    planDescription: $membershipPlan['plan_description'] ?? null,
                    priceCents: (int) ($membershipPlan['price_cents'] ?? $order->total_cents),
                    currency: (string) $order->currency,
                    periodMonths: (int) ($membershipPlan['period_months'] ?? 12),
                    memberEmail: (string) $order->buyer_email,
                    memberName: (string) $order->buyer_name,
                    order: $order,
                );
            }

            // Commit addon sales so the public list reflects accurate
            // remaining stock on the next read.
            $this->addonManager()->commitForOrder($session);

            // Apply any gift card balance staged at checkout. Idempotent
            // by virtue of the early `order_id` short-circuit above.
            $code = (string) (($session->attendee_data['_gift_card_code'] ?? '') ?: '');
            if ($code !== '') {
                $card = $this->giftCardManager()->findActive($code);
                if ($card && $card->currency === $session->currency) {
                    $absorbed = min((int) $card->balance_cents, (int) $order->total_cents);
                    if ($absorbed > 0) {
                        $this->giftCardManager()->redeem($card, $absorbed, $session, $order);
                    }
                }
            }

            $session->forceFill(['order_id' => $order->id])->save();

            // Bump the event-level cached counter so the organizer
            // dashboard reflects new sales without a heavy reaggregation.
            $issuedCount = (int) $order->items()->count();
            $session->event?->incrementTicketsSold($issuedCount);

            OrderPaid::dispatch($order);

            return $order;
        });
    }

    /**
     * Build the per-seat ticket. `qr_payload` is a UUID + HMAC pair —
     * decoded by the existing scanner pipeline. We piggyback the
     * scanner's QR scheme rather than minting a new one.
     */
    protected function issueOfflineTicket(CheckoutSession $session, TicketCategory $category, array $attendee): OfflineTicket
    {
        return $this->mintTicket(
            eventId: (int) $session->event_id,
            organisationUuid: (string) $session->organisation_id,
            category: $category,
        );
    }

    /**
     * Same issuance, but parameterised on event + org so the back-
     * office flow can mint tickets without a CheckoutSession.
     */
    public function issueOfflineTicketDirectly(Event $event, TicketCategory $category): OfflineTicket
    {
        return $this->mintTicket(
            eventId: (int) $event->id,
            organisationUuid: (string) $event->organisation_id,
            category: $category,
        );
    }

    protected function mintTicket(int $eventId, string $organisationUuid, TicketCategory $category): OfflineTicket
    {
        $uuid = (string) Str::uuid();
        $secret = (string) config('app.key');

        // qr_payload format mirrors what the scanner expects:
        //   <uuid>.<hmac>
        $hmac = substr(hash_hmac('sha256', $uuid, $secret), 0, 24);
        $payload = $uuid.'.'.$hmac;

        return OfflineTicket::create([
            'uuid' => $uuid,
            'ticket_category_id' => $category->id,
            'event_id' => $eventId,
            'organisation_id' => $organisationUuid,
            'ticket_number' => strtoupper(Str::random(10)),
            'qr_payload' => $payload,
            'pass_type' => $category->pass_type,
            'admission_type' => $category->admission_type,
            'serial' => Str::random(16),
            'scan_count' => 0,
            'log_count' => 0,
            'is_voided' => false,
        ]);
    }

    /**
     * Pivot the session's attendee_data JSON into
     * [item_id => [ {name, email, phone}, ... ]] for issuance.
     *
     * @return array<int, list<array<string, string|null>>>
     */
    protected function indexAttendees(CheckoutSession $session): array
    {
        $byItem = [];
        foreach ((array) ($session->attendee_data ?? []) as $entry) {
            $itemId = (int) ($entry['item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }
            $byItem[$itemId] = $entry['attendees'] ?? [];
        }

        return $byItem;
    }
}
