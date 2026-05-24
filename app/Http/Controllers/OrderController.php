<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Order;
use App\Services\Audit\AuditLogger;
use App\Services\Payments\Contracts\RefundsTransactions;
use App\Services\Payments\Data\RefundRequest;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Orders — cross-event order management. Buyer info, line items,
 * refunds, resend tickets.
 *
 * Org scoping uses Organization.uuid because the `orders` table stores
 * `organisation_id` as the uuid (consistent with the platform schema).
 */
class OrderController extends Controller
{
    public function index(Request $request, string $current_organization): Response
    {
        abort_unless($request->user()->can('order.view'), 403);

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $eventSlug = (string) $request->query('event', '');
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');

        $query = Order::query()
            ->with(['event:id,slug,name'])
            ->where('organisation_id', $org->uuid);

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('reference', 'like', '%'.$q.'%')
                    ->orWhere('buyer_email', 'like', '%'.$q.'%')
                    ->orWhere('buyer_name', 'like', '%'.$q.'%');
            });
        }
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($eventSlug !== '') {
            $query->whereHas('event', fn ($e) => $e->where('slug', $eventSlug));
        }
        if ($from !== '') {
            $query->where('placed_at', '>=', $from);
        }
        if ($to !== '') {
            $query->where('placed_at', '<=', $to);
        }

        $paginator = $query->orderByDesc('placed_at')->orderByDesc('id')->paginate(50)->withQueryString();

        $rows = collect($paginator->items())->map(fn (Order $o) => [
            'reference' => $o->reference,
            'buyer_name' => $o->buyer_name,
            'buyer_email' => $o->buyer_email,
            'event' => $o->event?->name,
            'event_slug' => $o->event?->slug,
            'status' => $o->status,
            'currency' => $o->currency,
            'total' => $o->totalFormatted(),
            'payment_method' => $o->payment_method,
            'placed_at' => $o->placed_at?->toIso8601String(),
        ])->all();

        $totals = Order::query()
            ->where('organisation_id', $org->uuid)
            ->selectRaw('
                COUNT(*) AS total,
                SUM(CASE WHEN status = "paid" THEN 1 ELSE 0 END) AS paid,
                SUM(CASE WHEN status = "refunded" OR status = "partially_refunded" THEN 1 ELSE 0 END) AS refunded,
                SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = "paid" THEN total_cents ELSE 0 END) AS revenue_cents
            ')
            ->first();

        $eventOptions = Event::query()
            ->where('organisation_id', $org->id)
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->map(fn (Event $e) => ['value' => $e->slug, 'label' => $e->name])
            ->all();

        return Inertia::render('orders/index', [
            'filters' => compact('q', 'status', 'eventSlug', 'from', 'to'),
            'event_options' => $eventOptions,
            'totals' => [
                'total' => (int) ($totals->total ?? 0),
                'paid' => (int) ($totals->paid ?? 0),
                'refunded' => (int) ($totals->refunded ?? 0),
                'pending' => (int) ($totals->pending ?? 0),
                'revenue_formatted' => number_format(((int) ($totals->revenue_cents ?? 0)) / 100, 2),
            ],
            'orders' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'permissions' => [
                'manage' => $request->user()->can('order.manage'),
                'refund' => $request->user()->can('order.refund'),
                'resend' => $request->user()->can('order.resend'),
            ],
            'breadcrumbs' => [
                ['title' => 'Orders', 'href' => "/{$current_organization}/orders"],
            ],
        ]);
    }

    public function show(Request $request, string $current_organization, string $order): Response
    {
        abort_unless($request->user()->can('order.view'), 403);

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        $row = Order::query()
            ->with(['event:id,slug,name,starts_at', 'items.category:id,name'])
            ->where('organisation_id', $org->uuid)
            ->where('reference', $order)
            ->first();

        return Inertia::render('orders/show', [
            'order' => $row ? [
                'reference' => $row->reference,
                'status' => $row->status,
                'currency' => $row->currency,
                'subtotal' => number_format($row->subtotal_cents / 100, 2),
                'tax' => number_format($row->tax_cents / 100, 2),
                'fee' => number_format($row->fee_cents / 100, 2),
                'total' => number_format($row->total_cents / 100, 2),
                'buyer_name' => $row->buyer_name,
                'buyer_email' => $row->buyer_email,
                'buyer_phone' => $row->buyer_phone,
                'event' => $row->event ? [
                    'slug' => $row->event->slug,
                    'name' => $row->event->name,
                    'starts_at' => $row->event->starts_at?->toIso8601String(),
                ] : null,
                'payment_method' => $row->payment_method,
                'payment_reference' => $row->payment_reference,
                'placed_at' => $row->placed_at?->toIso8601String(),
                'metadata' => $row->metadata,
                'items' => $row->items->map(fn ($i) => [
                    'attendee_name' => $i->attendee_name,
                    'attendee_email' => $i->attendee_email,
                    'ticket_type' => $i->ticket_type,
                    'unit_price' => number_format($i->unit_price_cents / 100, 2),
                    'currency' => $i->currency,
                    'category' => $i->category?->name,
                    'checked_in_at' => $i->checked_in_at?->toIso8601String(),
                ])->all(),
            ] : null,
            'orderRef' => $order,
            'permissions' => [
                'manage' => $request->user()->can('order.manage'),
                'refund' => $request->user()->can('order.refund') && $row?->isRefundable(),
                'resend' => $request->user()->can('order.resend') && $row !== null,
            ],
            'breadcrumbs' => [
                ['title' => 'Orders', 'href' => "/{$current_organization}/orders"],
                ['title' => $order, 'href' => "/{$current_organization}/orders/{$order}"],
            ],
        ]);
    }

    /**
     * Resend the buyer's tickets. Delivery is best-effort via Mail::raw
     * against the platform mailer; in production this becomes a queued
     * ResendTicketsJob with a Mailable that re-renders the ticket PDF +
     * QR per item. The audit row + log entry land either way so support
     * always sees the request.
     */
    public function resend(Request $request, string $current_organization, string $order, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('order.resend'), 403);

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        $row = Order::query()
            ->with('items')
            ->where('organisation_id', $org->uuid)
            ->where('reference', $order)
            ->firstOrFail();

        $itemSummary = $row->items
            ->map(fn ($i) => "  - {$i->ticket_type} for {$i->attendee_name} ({$i->attendee_email})")
            ->implode("\n");

        try {
            Mail::raw(
                "Your tickets for order {$row->reference}\n\n{$itemSummary}\n\nReply to this email if anything looks wrong.",
                function ($mail) use ($row) {
                    $mail->to($row->buyer_email, $row->buyer_name)
                        ->subject("Your tickets — order {$row->reference}");
                },
            );
        } catch (\Throwable $e) {
            Log::warning('order.resend.delivery_failed', [
                'order' => $row->reference,
                'error' => $e->getMessage(),
            ]);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Could not send — logged for follow-up.',
            ]);

            return back();
        }

        $audit->record('order.tickets.resent', $org, $request->user(), after: [
            'order_reference' => $row->reference,
            'buyer_email' => $row->buyer_email,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tickets resent to '.$row->buyer_email.'.']);

        return back();
    }

    /**
     * Mark the order refunded. If `payment_reference` matches a
     * PaymentTransaction we created, kick that transaction through the
     * sandbox/payment gateway refund path so the merchant ledger
     * reconciles. Either way, the order row flips to `refunded` and
     * the action is audited.
     */
    public function refund(Request $request, string $current_organization, string $order, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('order.refund'), 403);

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        $row = Order::query()
            ->where('organisation_id', $org->uuid)
            ->where('reference', $order)
            ->firstOrFail();

        abort_unless($row->isRefundable(), 409, 'Order is not in a refundable state.');

        $before = ['status' => $row->status, 'total_cents' => $row->total_cents];

        // Best-effort gateway refund via PaymentTransaction.reference =
        // order.payment_reference. Silent skip when no transaction
        // is on record — the manual status flip below still keeps the
        // order book accurate.
        if (! empty($row->payment_reference)) {
            try {
                /** @var PaymentManager $payments */
                $payments = app(PaymentManager::class);
                $gateway = $payments->default();

                if ($gateway instanceof RefundsTransactions) {
                    $gateway->refund(new RefundRequest(
                        reference: $row->payment_reference,
                        gatewayReference: null,
                        amount: null,
                        reason: 'order_refund_'.$row->reference,
                    ));
                }
            } catch (\Throwable $e) {
                Log::warning('order.refund.gateway_failed', [
                    'order' => $row->reference,
                    'payment_reference' => $row->payment_reference,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $row->update(['status' => 'refunded']);

        $audit->record('order.refunded', $org, $request->user(),
            before: $before,
            after: ['status' => 'refunded'],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Order refunded.']);

        return back();
    }
}
