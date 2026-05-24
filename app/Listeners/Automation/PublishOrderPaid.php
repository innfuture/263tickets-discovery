<?php

declare(strict_types=1);

namespace App\Listeners\Automation;

use App\Events\OrderPaid;
use App\Models\Organization;
use App\Services\Automation\AutomationDispatcher;

/**
 * OrderPaid → `order.paid` webhook to every subscribed integration.
 * Payload shape is what's most useful to downstream automation
 * tools (CRM upsert, Slack ping, Xero entry) — buyer, totals, ticket
 * list, attribution.
 */
class PublishOrderPaid
{
    public function __construct(protected AutomationDispatcher $dispatcher) {}

    public function handle(OrderPaid $event): void
    {
        $order = $event->order->loadMissing('items.category', 'event');
        $org = Organization::query()->where('uuid', $order->organisation_id)->first();
        if (! $org) {
            return;
        }

        $this->dispatcher->dispatch('order.paid', $org, [
            'order' => [
                'reference' => $order->reference,
                'uuid' => $order->uuid,
                'currency' => $order->currency,
                'subtotal_cents' => (int) $order->subtotal_cents,
                'discount_cents' => (int) ($order->discount_cents ?? 0),
                'tax_cents' => (int) $order->tax_cents,
                'fee_cents' => (int) $order->fee_cents,
                'total_cents' => (int) $order->total_cents,
                'payment_method' => $order->payment_method,
                'payment_reference' => $order->payment_reference,
                'promo_code_used' => $order->promo_code_used,
                'placed_at' => optional($order->placed_at)->toIso8601String(),
            ],
            'buyer' => [
                'name' => $order->buyer_name,
                'email' => $order->buyer_email,
                'phone' => $order->buyer_phone,
                'country_code' => $order->buyer_country_code,
            ],
            'event' => $order->event ? [
                'slug' => $order->event->slug,
                'name' => $order->event->name,
                'starts_at' => optional($order->event->starts_at)->toIso8601String(),
            ] : null,
            'items' => $order->items->map(fn ($i) => [
                'ticket_type' => $i->ticket_type,
                'attendee_name' => $i->attendee_name,
                'attendee_email' => $i->attendee_email,
                'unit_price_cents' => (int) $i->unit_price_cents,
            ])->all(),
            'attribution' => [
                'utm_source' => $order->utm_source,
                'utm_medium' => $order->utm_medium,
                'utm_campaign' => $order->utm_campaign,
            ],
        ]);
    }
}
