<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Automation;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Order;
use App\Services\Storefront\BackOfficeOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Write endpoints for orders — issue a comp / manual order from an
 * automation flow. Requires the `orders.write` scope.
 *
 *   POST /api/v1/automations/orders
 *
 * Mirrors what the back-office "issue manual order" controller does;
 * routes here so that an n8n "sponsor list arrived in our HRIS"
 * trigger can issue tickets without a human in the loop.
 */
class AutomationOrderController extends Controller
{
    public function __construct(protected BackOfficeOrderService $orders) {}

    public function store(Request $request): JsonResponse
    {
        $org = $request->attributes->get('automation_org');
        $token = $request->attributes->get('automation_token');

        $validated = $request->validate([
            'event_slug' => ['required', 'string'],
            'buyer_name' => ['required', 'string', 'max:191'],
            'buyer_email' => ['required', 'email', 'max:191'],
            'payment_method' => ['required', 'in:comp,cash,invoice,external'],
            'currency' => ['required', 'string', 'size:3'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ticket_category_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'items.*.unit_price_cents' => ['nullable', 'integer', 'min:0'],
            'items.*.attendee_name' => ['nullable', 'string', 'max:191'],
            'items.*.attendee_email' => ['nullable', 'email', 'max:191'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $event = Event::query()
            ->where('slug', $validated['event_slug'])
            ->where('organisation_id', $org->uuid)
            ->first();

        if (! $event) {
            return response()->json(['error' => 'event_not_found'], 404);
        }

        $order = $this->orders->issue($event, $validated);

        AuditLog::create([
            'organization_id' => $org->id,
            'actor_type' => 'api',
            'action' => 'order.issued.via_automation',
            'resource_type' => Order::class,
            'resource_id' => (string) $order->id,
            'after' => [
                'reference' => $order->reference,
                'token_label' => $token->label,
            ],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'data' => [
                'reference' => $order->reference,
                'uuid' => $order->uuid,
                'total_cents' => (int) $order->total_cents,
            ],
        ], 201);
    }
}
