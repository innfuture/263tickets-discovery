<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Automation;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read endpoints for automation tooling (n8n / Zapier polling
 * triggers, dashboards). All scoped to the token's organization.
 *
 *   GET /api/v1/automations/events                  list org's events
 *   GET /api/v1/automations/orders                  list orders (filtered)
 *   GET /api/v1/automations/orders/{reference}      one order with items
 */
class AutomationDataController extends Controller
{
    public function events(Request $request): JsonResponse
    {
        $org = $request->attributes->get('automation_org');

        $events = Event::query()
            ->where('organisation_id', $org->uuid)
            ->orderByDesc('starts_at')
            ->limit(min(500, (int) $request->integer('limit', 100)))
            ->get(['id', 'event_id', 'slug', 'name', 'status', 'visibility', 'starts_at', 'capacity', 'tickets_sold_count']);

        return response()->json([
            'data' => $events->map(fn ($e) => [
                'event_id' => $e->event_id,
                'slug' => $e->slug,
                'name' => $e->name,
                'status' => $e->status?->value,
                'visibility' => $e->visibility?->value,
                'starts_at' => optional($e->starts_at)->toIso8601String(),
                'capacity' => $e->capacity,
                'tickets_sold' => (int) $e->tickets_sold_count,
            ])->all(),
        ]);
    }

    public function orders(Request $request): JsonResponse
    {
        $org = $request->attributes->get('automation_org');

        $orders = Order::query()
            ->where('organisation_id', $org->uuid)
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('placed_after'), fn ($q, $d) => $q->where('placed_at', '>=', $d))
            ->when($request->query('placed_before'), fn ($q, $d) => $q->where('placed_at', '<=', $d))
            ->when($request->query('event_id'), fn ($q, $id) => $q->where('event_id', $id))
            ->orderByDesc('placed_at')
            ->limit(min(500, (int) $request->integer('limit', 100)))
            ->get();

        return response()->json([
            'data' => $orders->map(fn ($o) => $this->summary($o))->all(),
        ]);
    }

    public function show(Request $request, string $reference): JsonResponse
    {
        $org = $request->attributes->get('automation_org');

        $order = Order::query()
            ->with('items', 'event')
            ->where('organisation_id', $org->uuid)
            ->where('reference', $reference)
            ->first();

        if (! $order) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $payload = $this->summary($order);
        $payload['items'] = $order->items->map(fn ($i) => [
            'ticket_type' => $i->ticket_type,
            'attendee_name' => $i->attendee_name,
            'attendee_email' => $i->attendee_email,
            'unit_price_cents' => (int) $i->unit_price_cents,
        ])->all();

        return response()->json(['data' => $payload]);
    }

    /** @return array<string, mixed> */
    protected function summary(Order $order): array
    {
        return [
            'reference' => $order->reference,
            'uuid' => $order->uuid,
            'status' => $order->status,
            'currency' => $order->currency,
            'total_cents' => (int) $order->total_cents,
            'placed_at' => optional($order->placed_at)->toIso8601String(),
            'buyer' => [
                'name' => $order->buyer_name,
                'email' => $order->buyer_email,
                'phone' => $order->buyer_phone,
            ],
            'event' => $order->event ? [
                'slug' => $order->event->slug,
                'name' => $order->event->name,
            ] : null,
        ];
    }
}
