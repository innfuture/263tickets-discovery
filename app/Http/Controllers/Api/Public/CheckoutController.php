<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Enums\CheckoutSessionStatus;
use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\CheckoutSession;
use App\Models\Event;
use App\Models\Organization;
use App\Models\PaymentTransaction;
use App\Services\Payments\Data\ChargeRequest as PaymentChargeRequest;
use App\Services\Payments\Data\Customer;
use App\Services\Payments\Data\Money;
use App\Services\Payments\PaymentManager;
use App\Services\Storefront\CheckoutSessionManager;
use App\Services\Storefront\Data\CartLineInput;
use App\Services\Storefront\Exceptions\CheckoutSessionLockedException;
use App\Services\Storefront\Exceptions\CurrencyMismatchException;
use App\Services\Storefront\Exceptions\InventoryUnavailableException;
use App\Services\Storefront\Exceptions\PromoCodeInvalidException;
use App\Services\Storefront\OrderFulfillment;
use App\Services\Storefront\PriceCalculator;
use App\Services\Storefront\PromoCodeValidator;
use App\Services\Storefront\TicketReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The public storefront's full checkout flow. Stateless — every call
 * carries the session UUID in the URL; nothing is stored in the
 * browser session.
 *
 * Flow:
 *
 *   POST   /sessions                     → create
 *   GET    /sessions/{uuid}              → snapshot (totals, items, items)
 *   PATCH  /sessions/{uuid}/items        → replace line items (add/update/remove)
 *   PATCH  /sessions/{uuid}/attendees    → buyer + per-seat attendees
 *   POST   /sessions/{uuid}/promo        → apply / clear promo code
 *   POST   /sessions/{uuid}/pay          → initiate payment with chosen gateway
 *   POST   /sessions/{uuid}/confirm      → poll status / handshake after redirect
 *   DELETE /sessions/{uuid}              → abandon
 *
 * Every mutation goes through CheckoutSessionManager / TicketReservation
 * / PriceCalculator. The controller is just transport + validation.
 */
class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutSessionManager $sessions,
        protected TicketReservation $reservation,
        protected PriceCalculator $prices,
        protected PromoCodeValidator $promos,
        protected PaymentManager $payments,
        protected OrderFulfillment $fulfilment,
    ) {}

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_slug' => ['required', 'string'],
            'currency' => ['nullable', 'string', 'size:3'],
            'idempotency_key' => ['nullable', 'string', 'max:80'],
            'attribution' => ['nullable', 'array'],
            'attribution.utm_source' => ['nullable', 'string', 'max:80'],
            'attribution.utm_medium' => ['nullable', 'string', 'max:80'],
            'attribution.utm_campaign' => ['nullable', 'string', 'max:80'],
            'attribution.utm_content' => ['nullable', 'string', 'max:191'],
            'attribution.utm_term' => ['nullable', 'string', 'max:191'],
            'attribution.referral_source' => ['nullable', 'string', 'max:191'],
            'attribution.buyer_locale' => ['nullable', 'string', 'max:10'],
            'attribution.buyer_country_code' => ['nullable', 'string', 'size:2'],
        ]);

        $event = Event::query()->where('slug', $validated['event_slug'])->first();
        if (! $event || $event->status !== EventStatus::Published) {
            return response()->json(['error' => 'event_not_purchasable'], 422);
        }

        $currency = strtoupper($validated['currency'] ?? ($event->ticketCategories->first()->base_currency ?? 'USD'));

        try {
            $session = $this->sessions->create(
                event: $event,
                currency: $currency,
                request: $request,
                attribution: (array) ($validated['attribution'] ?? []),
                idempotencyKey: $validated['idempotency_key'] ?? null,
            );
        } catch (CheckoutSessionLockedException $e) {
            return response()->json(['error' => 'rate_limited', 'message' => $e->getMessage()], 429);
        }

        return response()->json(['data' => $this->snapshot($session)], 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $session = $this->locate($uuid);
        if (! $session) {
            return response()->json(['error' => 'session_not_found'], 404);
        }

        return response()->json(['data' => $this->snapshot($session)]);
    }

    public function updateItems(Request $request, string $uuid): JsonResponse
    {
        $session = $this->locate($uuid);
        if (! $session) {
            return response()->json(['error' => 'session_not_found'], 404);
        }
        if (! $session->isMutable()) {
            return response()->json(['error' => 'session_locked'], 409);
        }

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.ticket_category_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:0', 'max:50'],
        ]);

        $lines = array_map(
            fn ($row) => new CartLineInput((int) $row['ticket_category_id'], (int) $row['quantity']),
            $validated['items'],
        );

        try {
            $this->reservation->syncLines($session, $lines);
        } catch (InventoryUnavailableException $e) {
            return response()->json([
                'error' => 'inventory_unavailable',
                'message' => $e->getMessage(),
                'ticket_category_id' => $e->ticketCategoryId,
                'requested' => $e->requested,
                'available' => $e->available,
            ], 422);
        } catch (CurrencyMismatchException $e) {
            return response()->json([
                'error' => 'currency_mismatch',
                'ticket_category_id' => $e->ticketCategoryId,
                'cart_currency' => $e->cartCurrency,
                'message' => $e->getMessage(),
            ], 422);
        }

        $this->sessions->touch($session);
        $this->prices->recompute($session->refresh()->load('items'));

        return response()->json(['data' => $this->snapshot($session->refresh())]);
    }

    public function updateAttendees(Request $request, string $uuid): JsonResponse
    {
        $session = $this->locate($uuid);
        if (! $session) {
            return response()->json(['error' => 'session_not_found'], 404);
        }
        if (! $session->isMutable()) {
            return response()->json(['error' => 'session_locked'], 409);
        }

        $validated = $request->validate([
            'buyer_name' => ['required', 'string', 'max:191'],
            'buyer_email' => ['required', 'email', 'max:191'],
            'buyer_phone' => ['nullable', 'string', 'max:40'],
            'buyer_country_code' => ['nullable', 'string', 'size:2'],
            'attendees' => ['nullable', 'array'],
            'attendees.*.item_id' => ['required_with:attendees', 'integer'],
            'attendees.*.attendees' => ['required_with:attendees', 'array'],
            'attendees.*.attendees.*.name' => ['required_with:attendees.*.attendees', 'string', 'max:191'],
            'attendees.*.attendees.*.email' => ['nullable', 'email', 'max:191'],
            'attendees.*.attendees.*.phone' => ['nullable', 'string', 'max:40'],
        ]);

        $session->fill([
            'buyer_name' => $validated['buyer_name'],
            'buyer_email' => strtolower($validated['buyer_email']),
            'buyer_phone' => $validated['buyer_phone'] ?? null,
            'buyer_country_code' => $validated['buyer_country_code'] ?? null,
            'attendee_data' => $validated['attendees'] ?? [],
        ])->save();

        $this->sessions->touch($session);

        return response()->json(['data' => $this->snapshot($session)]);
    }

    public function applyPromo(Request $request, string $uuid): JsonResponse
    {
        $session = $this->locate($uuid);
        if (! $session) {
            return response()->json(['error' => 'session_not_found'], 404);
        }
        if (! $session->isMutable()) {
            return response()->json(['error' => 'session_locked'], 409);
        }

        $validated = $request->validate(['code' => ['required', 'string', 'max:64']]);

        try {
            $promo = $this->promos->resolve($validated['code'], $session->loadMissing('items'));
        } catch (PromoCodeInvalidException $e) {
            return response()->json([
                'error' => 'promo_invalid',
                'reason' => $e->reasonCode,
                'message' => $e->getMessage(),
            ], 422);
        }

        $session->forceFill([
            'promo_code_id' => $promo->id,
            'promo_code_snapshot' => $promo->code,
        ])->save();

        $this->prices->recompute($session);

        return response()->json(['data' => $this->snapshot($session->refresh())]);
    }

    public function clearPromo(string $uuid): JsonResponse
    {
        $session = $this->locate($uuid);
        if (! $session || ! $session->isMutable()) {
            return response()->json(['error' => 'session_locked'], 409);
        }

        $session->forceFill([
            'promo_code_id' => null,
            'promo_code_snapshot' => null,
        ])->save();
        $this->prices->recompute($session);

        return response()->json(['data' => $this->snapshot($session->refresh())]);
    }

    /**
     * Lock the session and initiate a charge with the chosen gateway.
     * Returns the gateway's redirect URL (or instructions) so the
     * caller can navigate the buyer to the payment surface.
     */
    public function pay(Request $request, string $uuid): JsonResponse
    {
        $session = $this->locate($uuid);
        if (! $session) {
            return response()->json(['error' => 'session_not_found'], 404);
        }
        if (! $session->isMutable()) {
            return response()->json(['error' => 'session_locked'], 409);
        }
        if (! $session->buyer_email) {
            return response()->json(['error' => 'buyer_details_required'], 422);
        }
        if ($session->items()->count() === 0) {
            return response()->json(['error' => 'cart_empty'], 422);
        }

        $validated = $request->validate([
            'gateway' => ['required', 'string'],
            'return_url' => ['nullable', 'url'],
        ]);

        try {
            $gateway = $this->payments->gateway($validated['gateway']);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'gateway_unavailable', 'message' => $e->getMessage()], 422);
        }

        // Authoritative price snapshot. Don't trust totals from the buyer.
        $quote = $this->prices->recompute($session);

        $this->sessions->lockForPayment($session);

        // payment_transactions keys on the integer FK (organization_id),
        // not the platform-wide UUID — look up the org so the gateway
        // row joins back into reports correctly.
        $org = Organization::query()->where('uuid', $session->organisation_id)->first();

        $transaction = PaymentTransaction::create([
            'organization_id' => $org?->id,
            'amount_minor' => $quote->totalMinor,
            'currency' => $session->currency,
            'gateway' => $gateway->identifier(),
            'status' => 'pending',
            'reference' => 'CHK-'.$session->uuid,
            'customer_email' => $session->buyer_email,
            'customer_msisdn' => $session->buyer_phone,
            'customer_name' => $session->buyer_name,
            'customer_ip' => $session->ip_address,
            // Polymorphic owner — points at the session until fulfilment,
            // then the observer repoints it at the issued Order.
            'payable_type' => CheckoutSession::class,
            'payable_id' => $session->id,
            'metadata' => [
                'checkout_session_uuid' => $session->uuid,
                'event_id' => (int) $session->event_id,
            ],
        ]);
        $session->forceFill(['payment_transaction_id' => $transaction->id])->save();

        $charge = $gateway->charge(new PaymentChargeRequest(
            amount: new Money($quote->totalMinor, $session->currency),
            customer: new Customer(
                name: (string) $session->buyer_name,
                email: (string) $session->buyer_email,
                msisdn: $session->buyer_phone,
                ipAddress: $session->ip_address,
            ),
            reference: $transaction->reference,
            description: 'Tickets — '.optional($session->event)->name,
            returnUrl: $validated['return_url'] ?? null,
            resultUrl: route('payments.webhook', ['gateway' => $gateway->identifier()]),
            metadata: ['checkout_session_uuid' => $session->uuid],
        ));

        $transaction->forceFill([
            'gateway_reference' => $charge->gatewayReference,
            'status' => $charge->status->value,
        ])->save();

        // requires_action surfaces 3DS / SCA challenges. Drivers that
        // support these set `status = AUTHORIZED` with an in-flight
        // redirect_url that's a challenge page rather than the final
        // hosted checkout. The front-end iframe-renders it.
        $requiresAction = $charge->status->value === 'authorized'
            || (! empty($charge->redirectUrl) && $charge->status->value !== 'captured');

        return response()->json([
            'data' => [
                'session' => $this->snapshot($session->refresh()),
                'payment' => [
                    'status' => $charge->status->value,
                    'requires_action' => $requiresAction,
                    'redirect_url' => $charge->redirectUrl,
                    'poll_url' => $charge->pollUrl,
                    'instructions' => $charge->instructions,
                    'gateway' => $gateway->identifier(),
                    'transaction_reference' => $transaction->reference,
                ],
            ],
        ]);
    }

    /**
     * Buyer-side poll after the gateway redirect. If the gateway has
     * settled, we fulfil here too so the buyer doesn't have to wait
     * for the webhook in the happy path.
     */
    public function confirm(string $uuid): JsonResponse
    {
        $session = $this->locate($uuid);
        if (! $session) {
            return response()->json(['error' => 'session_not_found'], 404);
        }

        if ($session->status === CheckoutSessionStatus::Completed && $session->order_id) {
            return response()->json([
                'data' => [
                    'status' => 'completed',
                    'order_reference' => $session->order?->reference,
                    'order_uuid' => $session->order?->uuid,
                ],
            ]);
        }

        $tx = $session->paymentTransaction;
        if ($tx && in_array((string) $tx->status, ['captured', 'authorized'], true)) {
            $order = $this->fulfilment->fulfill($session, $tx->reference, $tx->gateway);
            $this->sessions->complete($session->refresh());

            return response()->json([
                'data' => [
                    'status' => 'completed',
                    'order_reference' => $order->reference,
                    'order_uuid' => $order->uuid,
                ],
            ]);
        }

        return response()->json(['data' => ['status' => $session->status?->value]]);
    }

    public function abandon(string $uuid): JsonResponse
    {
        $session = $this->locate($uuid);
        if (! $session) {
            return response()->json(['error' => 'session_not_found'], 404);
        }
        if ($session->status?->isTerminal()) {
            return response()->json(['data' => $this->snapshot($session)]);
        }

        $this->sessions->cancel($session);

        return response()->json(['data' => $this->snapshot($session->refresh())]);
    }

    protected function locate(string $uuid): ?CheckoutSession
    {
        return CheckoutSession::query()
            ->with(['items.category', 'event', 'order'])
            ->where('uuid', $uuid)
            ->first();
    }

    /** @return array<string, mixed> */
    protected function snapshot(CheckoutSession $session): array
    {
        $session->loadMissing('items.category', 'event');

        return [
            'uuid' => $session->uuid,
            'status' => $session->status?->value,
            'expires_at' => optional($session->expires_at)->toIso8601String(),
            'currency' => $session->currency,
            'totals' => [
                'subtotal_cents' => (int) $session->subtotal_cents,
                'discount_cents' => (int) $session->discount_cents,
                'tax_cents' => (int) $session->tax_cents,
                'fee_cents' => (int) $session->fee_cents,
                'total_cents' => (int) $session->total_cents,
            ],
            'buyer' => [
                'name' => $session->buyer_name,
                'email' => $session->buyer_email,
                'phone' => $session->buyer_phone,
                'country_code' => $session->buyer_country_code,
            ],
            'promo_code' => $session->promo_code_snapshot,
            'event' => $session->event ? [
                'slug' => $session->event->slug,
                'name' => $session->event->name,
                'starts_at' => optional($session->event->starts_at)->toIso8601String(),
            ] : null,
            'items' => $session->items->map(fn ($item) => [
                'id' => (int) $item->id,
                'ticket_category_id' => (int) $item->ticket_category_id,
                'ticket_name' => $item->category?->name,
                'quantity' => (int) $item->quantity,
                'unit_price_cents' => (int) $item->unit_price_cents,
                'line_total_cents' => (int) $item->line_total_cents,
                'currency' => (string) $item->currency,
            ])->all(),
            'order_reference' => $session->order?->reference,
        ];
    }
}
