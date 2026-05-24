<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\TicketCategory;
use App\Services\Buyers\SelfServiceTicketService;
use App\Services\Storefront\TicketTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 *   POST  /api/v1/buyer/orders/{ref}/items/{id}/upgrade   { new_tier_id }
 *   POST  /api/v1/buyer/orders/{ref}/items/{id}/downgrade { new_tier_id }
 *   POST  /api/v1/buyer/orders/{ref}/items/{id}/void
 *   POST  /api/v1/buyer/orders/{ref}/items/{id}/transfer  { to_email, … }
 *
 * All authenticated via EnsureBuyerAuth. Ownership is checked inside
 * the services — a buyer can only act on tickets attached to their
 * own buyer_id.
 */
class BuyerSelfServiceController extends Controller
{
    public function __construct(
        protected SelfServiceTicketService $self,
        protected TicketTransferService $transfers,
    ) {}

    public function upgrade(Request $request, string $reference, int $itemId): JsonResponse
    {
        [$buyer, $item] = $this->locate($request, $reference, $itemId);

        $validated = $request->validate(['new_tier_id' => ['required', 'integer']]);
        $tier = TicketCategory::query()->find($validated['new_tier_id']);
        if (! $tier) {
            return response()->json(['error' => 'tier_not_found'], 404);
        }

        try {
            $result = $this->self->upgrade($buyer, $item, $tier);
        } catch (RuntimeException $e) {
            return response()->json(['error' => 'upgrade_failed', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    public function downgrade(Request $request, string $reference, int $itemId): JsonResponse
    {
        [$buyer, $item] = $this->locate($request, $reference, $itemId);

        $validated = $request->validate(['new_tier_id' => ['required', 'integer']]);
        $tier = TicketCategory::query()->find($validated['new_tier_id']);
        if (! $tier) {
            return response()->json(['error' => 'tier_not_found'], 404);
        }

        try {
            $refund = $this->self->downgrade($buyer, $item, $tier);
        } catch (RuntimeException $e) {
            return response()->json(['error' => 'downgrade_failed', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => [
            'refund_request_uuid' => $refund->uuid,
            'status' => $refund->status,
        ]]);
    }

    public function void(Request $request, string $reference, int $itemId): JsonResponse
    {
        [$buyer, $item] = $this->locate($request, $reference, $itemId);

        $validated = $request->validate([
            'reason_code' => ['nullable', 'in:duplicate_purchase,event_cancelled,date_change,not_attending,fraud,other'],
        ]);

        try {
            $refund = $this->self->void($buyer, $item, $validated['reason_code'] ?? 'not_attending');
        } catch (RuntimeException $e) {
            return response()->json(['error' => 'void_failed', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => [
            'refund_request_uuid' => $refund->uuid,
            'status' => $refund->status,
        ]]);
    }

    public function transfer(Request $request, string $reference, int $itemId): JsonResponse
    {
        [$buyer, $item] = $this->locate($request, $reference, $itemId);

        $validated = $request->validate([
            'to_email' => ['required', 'email', 'max:191'],
            'to_name' => ['nullable', 'string', 'max:191'],
            'message' => ['nullable', 'string', 'max:2000'],
            'sale_price_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $transfer = $this->transfers->offer(
                sourceItem: $item,
                toEmail: $validated['to_email'],
                toName: $validated['to_name'] ?? null,
                message: $validated['message'] ?? null,
                salePriceCents: $validated['sale_price_cents'] ?? null,
                currency: $item->currency,
            );
        } catch (RuntimeException $e) {
            return response()->json(['error' => 'transfer_failed', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => [
            'uuid' => $transfer->uuid,
            'expires_at' => optional($transfer->expires_at)->toIso8601String(),
        ]], 201);
    }

    /** @return array{0: \App\Models\Buyer, 1: OrderItem} */
    protected function locate(Request $request, string $reference, int $itemId): array
    {
        $buyer = $request->attributes->get('buyer');
        $item = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('reference', $reference)->where('buyer_id', $buyer->id))
            ->with('order')
            ->find($itemId);

        if (! $item) {
            abort(404, 'Ticket not found.');
        }

        return [$buyer, $item];
    }
}
