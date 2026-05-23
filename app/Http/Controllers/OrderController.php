<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Orders is the cross-event order management surface — buyer info,
 * refunds, resend tickets, view + edit individual orders.
 *
 * There is no Order model in the codebase yet (tickets land directly
 * in `tickets` / `offline_tickets` without an order envelope), so the
 * controller currently ships an empty-state payload with the filter
 * surface and column shapes pre-defined. When the Order model lands,
 * this controller switches from "empty list" to "real query" without
 * the page contract changing.
 */
class OrderController extends Controller
{
    public function index(Request $request, string $current_organization): Response
    {
        abort_unless($request->user()->can('order.view'), 403);

        return Inertia::render('orders/index', [
            'filters' => [
                'q' => $request->string('q')->value(),
                'status' => $request->string('status')->value(),
                'event' => $request->string('event')->value(),
                'from' => $request->string('from')->value(),
                'to' => $request->string('to')->value(),
            ],
            // Empty payload until the Order model exists. Shape is
            // pinned so the React page can render the table layout.
            'orders' => [
                'data' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0],
            ],
            'permissions' => [
                'manage' => $request->user()->can('order.manage'),
                'refund' => $request->user()->can('order.refund'),
                'resend' => $request->user()->can('order.resend'),
            ],
        ]);
    }

    public function show(Request $request, string $current_organization, string $order): Response
    {
        abort_unless($request->user()->can('order.view'), 403);

        // No Order model yet — return null so the page renders its
        // not-found / empty state. Once persisted orders land, look
        // them up here and pass through.
        return Inertia::render('orders/show', [
            'order' => null,
            'orderRef' => $order,
            'permissions' => [
                'manage' => $request->user()->can('order.manage'),
                'refund' => $request->user()->can('order.refund'),
                'resend' => $request->user()->can('order.resend'),
            ],
        ]);
    }
}
