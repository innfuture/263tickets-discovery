<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Distribution;

use App\Http\Controllers\Controller;
use App\Models\Distributor;
use App\Models\DistributorDevice;
use App\Models\TicketDispatch;
use App\Services\Distribution\DispatchReceiver;
use App\Services\Distribution\Exceptions\DispatchException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Distributor acknowledges receipt of a dispatch. Verifies the seal
 * matches, then flips the dispatch to `received` and writes the
 * per-ticket `received` ledger entries.
 *
 * Dispute path: same endpoint with `?dispute=1` and a reason in the
 * body.
 */
class DispatchReceiptController extends Controller
{
    public function __construct(protected DispatchReceiver $receiver) {}

    public function receive(Request $request, string $dispatchUuid): JsonResponse
    {
        $device = $request->attributes->get('distributor_device');
        $distributor = $request->attributes->get('distributor');
        if (! $device instanceof DistributorDevice || ! $distributor instanceof Distributor) {
            return response()->json(['error' => 'device_unresolved'], 401);
        }

        $dispatch = TicketDispatch::query()->where('uuid', $dispatchUuid)->first();
        if (! $dispatch) {
            return response()->json(['error' => 'dispatch_not_found'], 404);
        }

        $validated = $request->validate([
            'observed_seal_id' => ['nullable', 'string', 'max:64'],
            'observed_photo_hash' => ['nullable', 'string', 'size:64'],
            'dispute' => ['nullable', 'boolean'],
            'dispute_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            if (! empty($validated['dispute'])) {
                $reason = (string) ($validated['dispute_reason'] ?? 'unspecified');
                $dispatch = $this->receiver->dispute($dispatch, $distributor, $device->id, $reason);
            } else {
                $dispatch = $this->receiver->receive(
                    dispatch: $dispatch,
                    recipient: $distributor,
                    actorUserId: $device->id,
                    observedSealId: $validated['observed_seal_id'] ?? null,
                    observedPhotoHash: $validated['observed_photo_hash'] ?? null,
                );
            }
        } catch (DispatchException $e) {
            return response()->json([
                'error' => $e->errorCode,
                'message' => $e->getMessage(),
            ], match ($e->errorCode) {
                'signature_invalid' => 401,
                'wrong_recipient' => 403,
                'not_receivable', 'not_disputable' => 409,
                default => 400,
            });
        }

        return response()->json([
            'data' => [
                'dispatch_uuid' => $dispatch->uuid,
                'status' => $dispatch->status,
                'received_at' => $dispatch->received_at?->toIso8601String(),
                'disputed_at' => $dispatch->disputed_at?->toIso8601String(),
            ],
        ]);
    }
}
