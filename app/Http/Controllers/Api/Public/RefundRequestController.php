<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Storefront\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Buyer-side refund initiation. The actual gateway-side refund is
 * the organizer's call from the dashboard (existing PaymentRefund
 * pipeline). This endpoint just creates the workflow ticket.
 *
 *   POST /api/v1/public/orders/{reference}/refund-request
 *   body: { contact_email, reason_code, notes? }
 */
class RefundRequestController extends Controller
{
    public function __construct(protected RefundService $refunds) {}

    public function store(Request $request, string $reference): JsonResponse
    {
        $order = Order::query()->where('reference', $reference)->first();
        if (! $order) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $validated = $request->validate([
            'contact_email' => ['required', 'email', 'max:191'],
            'reason_code' => ['required', 'in:duplicate_purchase,event_cancelled,date_change,not_attending,fraud,other'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Constant-time email match — the order's buyer must request
        // the refund (or someone with the matching email on the order).
        if (! hash_equals(
            strtolower((string) $order->buyer_email),
            strtolower((string) $validated['contact_email']),
        )) {
            return response()->json(['error' => 'not_found'], 404);
        }

        if (! $order->isRefundable()) {
            return response()->json([
                'error' => 'not_refundable',
                'message' => __('storefront.refund.not_refundable'),
            ], 422);
        }

        $entry = $this->refunds->submit($order, $validated, $request);

        return response()->json([
            'data' => [
                'uuid' => $entry->uuid,
                'status' => $entry->status,
                'order_reference' => $order->reference,
            ],
        ], 201);
    }
}
