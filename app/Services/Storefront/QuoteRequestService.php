<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\CheckoutSession;
use App\Models\Event;
use App\Models\QuoteRequest;
use App\Models\TicketCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Public-side quote intake + the conversion path back to a checkout
 * session once the organizer has responded with a price.
 */
class QuoteRequestService
{
    public function __construct(protected CheckoutSessionManager $sessions) {}

    public function submit(Event $event, array $input, Request $request): QuoteRequest
    {
        return QuoteRequest::create([
            'organisation_id' => $event->organisation_id,
            'event_id' => $event->id,
            'ticket_category_id' => $input['ticket_category_id'] ?? null,
            'company_name' => $input['company_name'] ?? null,
            'contact_name' => $input['contact_name'],
            'contact_email' => strtolower((string) $input['contact_email']),
            'contact_phone' => $input['contact_phone'] ?? null,
            'quantity_requested' => max(1, (int) $input['quantity_requested']),
            'notes' => $input['notes'] ?? null,
            'status' => QuoteRequest::STATUS_PENDING,
            'ip_address' => $request->ip(),
        ]);
    }

    /**
     * Organizer accepts the quote (via the dashboard) — we pre-fill a
     * checkout session with the quoted unit price as a per-tier
     * snapshot and hand the UUID back so the buyer can resume the flow.
     */
    public function convertToCheckout(QuoteRequest $quote, Request $request): CheckoutSession
    {
        $event = $quote->event;
        $session = $this->sessions->create(
            event: $event,
            currency: (string) ($quote->quoted_currency ?? $event->ticketCategories->first()->base_currency ?? 'USD'),
            request: $request,
            attribution: ['referral_source' => 'quote:'.$quote->uuid],
        );
        $session->forceFill([
            'buyer_email' => $quote->contact_email,
            'buyer_name' => $quote->contact_name,
            'buyer_phone' => $quote->contact_phone,
        ])->save();

        if ($quote->ticket_category_id) {
            $tier = TicketCategory::query()->find($quote->ticket_category_id);
            if ($tier) {
                $session->items()->create([
                    'ticket_category_id' => $tier->id,
                    'quantity' => $quote->quantity_requested,
                    'unit_price_cents' => (int) ($quote->quoted_unit_price_cents ?? 0),
                    'currency' => $session->currency,
                    'line_total_cents' => (int) ($quote->quoted_unit_price_cents ?? 0)
                        * $quote->quantity_requested,
                    'price_locked_at' => Carbon::now(),
                ]);
            }
        }

        $quote->forceFill([
            'status' => QuoteRequest::STATUS_ACCEPTED,
            'converted_order_id' => null,
        ])->save();

        return $session;
    }
}
