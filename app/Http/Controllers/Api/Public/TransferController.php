<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\TicketTransfer;
use App\Services\Storefront\TicketTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 *   POST   /api/v1/public/orders/{ref}/items/{item}/transfer  (signed URL)
 *   GET    /api/v1/public/transfers/claim/{token}             — recipient inspects
 *   POST   /api/v1/public/transfers/claim/{token}/accept
 *   POST   /api/v1/public/transfers/claim/{token}/decline
 *   POST   /api/v1/public/orders/{ref}/items/{item}/transfer/{uuid}/revoke (signed)
 */
class TransferController extends Controller
{
    public function __construct(protected TicketTransferService $transfers) {}

    public function offer(Request $request, string $reference, int $itemId): JsonResponse
    {
        if (! $request->hasValidSignature()) {
            return response()->json(['error' => 'signature_invalid'], 403);
        }

        $item = OrderItem::query()
            ->with('order')
            ->whereHas('order', fn ($q) => $q->where('reference', $reference))
            ->find($itemId);
        if (! $item) {
            return response()->json(['error' => 'not_found'], 404);
        }

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

        return response()->json([
            'data' => [
                'uuid' => $transfer->uuid,
                'claim_token' => $transfer->claim_token,
                'expires_at' => optional($transfer->expires_at)->toIso8601String(),
            ],
        ], 201);
    }

    public function show(string $token): JsonResponse
    {
        $transfer = $this->locate($token);
        if (! $transfer) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json(['data' => $this->payload($transfer)]);
    }

    public function accept(string $token): JsonResponse
    {
        $transfer = $this->locate($token);
        if (! $transfer) {
            return response()->json(['error' => 'not_found'], 404);
        }
        try {
            $this->transfers->claim($transfer);
            $this->transfers->accept($transfer);
        } catch (RuntimeException $e) {
            return response()->json(['error' => 'transfer_failed', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->payload($transfer->refresh())]);
    }

    public function decline(string $token): JsonResponse
    {
        $transfer = $this->locate($token);
        if (! $transfer) {
            return response()->json(['error' => 'not_found'], 404);
        }
        $this->transfers->decline($transfer);

        return response()->json(['data' => $this->payload($transfer->refresh())]);
    }

    public function revoke(Request $request, string $reference, int $itemId, string $uuid): JsonResponse
    {
        if (! $request->hasValidSignature()) {
            return response()->json(['error' => 'signature_invalid'], 403);
        }

        $transfer = TicketTransfer::query()->where('uuid', $uuid)->first();
        if (! $transfer) {
            return response()->json(['error' => 'not_found'], 404);
        }
        $this->transfers->revoke($transfer);

        return response()->json(['data' => $this->payload($transfer->refresh())]);
    }

    protected function locate(string $token): ?TicketTransfer
    {
        return TicketTransfer::query()->where('claim_token', $token)->first();
    }

    /** @return array<string, mixed> */
    protected function payload(TicketTransfer $t): array
    {
        return [
            'uuid' => $t->uuid,
            'status' => $t->status,
            'from_email' => $t->from_email,
            'to_email' => $t->to_email,
            'to_name' => $t->to_name,
            'message' => $t->message,
            'sale_price_cents' => $t->sale_price_cents,
            'currency' => $t->currency,
            'expires_at' => optional($t->expires_at)->toIso8601String(),
            'claimed_at' => optional($t->claimed_at)->toIso8601String(),
            'completed_at' => optional($t->completed_at)->toIso8601String(),
        ];
    }
}
