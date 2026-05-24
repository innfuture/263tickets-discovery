<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Distribution;

use App\Http\Controllers\Controller;
use App\Models\Distributor;
use App\Models\DistributorDevice;
use App\Models\OfflineTicket;
use App\Services\Distribution\Exceptions\SaleException;
use App\Services\Distribution\SalesRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/v1/distributor/sales
 *
 * Single-ticket sale endpoint. The terminal scans a QR, captures
 * customer + price, posts here. See SalesRecorder for the
 * anti-fraud + activation logic.
 */
class DistributorSalesController extends Controller
{
    public function __construct(protected SalesRecorder $recorder) {}

    public function store(Request $request): JsonResponse
    {
        $device = $request->attributes->get('distributor_device');
        $distributor = $request->attributes->get('distributor');
        if (! $device instanceof DistributorDevice || ! $distributor instanceof Distributor) {
            return response()->json(['error' => 'device_unresolved'], 401);
        }

        $validated = $request->validate([
            'ticket_uuid' => ['required', 'uuid'],
            'amount_cents' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'customer_phone' => ['nullable', 'string', 'max:32'],
            'customer_name' => ['nullable', 'string', 'max:191'],
            'customer_email' => ['nullable', 'email', 'max:191'],
            'gps_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'gps_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'signature' => ['nullable', 'string', 'max:2048'],
        ]);

        $ticket = OfflineTicket::query()
            ->where('uuid', $validated['ticket_uuid'])
            ->with('event')
            ->first();

        if (! $ticket) {
            return response()->json(['error' => 'ticket_not_found'], 404);
        }

        try {
            $sale = $this->recorder->record(
                distributor: $distributor,
                device: $device,
                ticket: $ticket,
                amountCents: (int) $validated['amount_cents'],
                currency: strtoupper((string) $validated['currency']),
                context: array_filter([
                    'customer_phone' => $validated['customer_phone'] ?? null,
                    'customer_name' => $validated['customer_name'] ?? null,
                    'customer_email' => $validated['customer_email'] ?? null,
                    'gps_lat' => isset($validated['gps_lat']) ? (float) $validated['gps_lat'] : null,
                    'gps_lng' => isset($validated['gps_lng']) ? (float) $validated['gps_lng'] : null,
                    'signature' => $validated['signature'] ?? null,
                ], fn ($v) => $v !== null),
            );
        } catch (SaleException $e) {
            return response()->json([
                'error' => $e->errorCode,
                'message' => $e->getMessage(),
            ], match ($e->errorCode) {
                'velocity_exceeded' => 429,
                'already_sold', 'ticket_state_invalid' => 409,
                'distributor_inactive', 'device_invalid' => 403,
                'outside_geofence', 'outside_sale_window' => 422,
                default => 400,
            });
        }

        return response()->json([
            'data' => [
                'sale_uuid' => $sale->uuid,
                'ticket_uuid' => $sale->ticket_uuid,
                'amount_cents' => $sale->amount_cents,
                'currency' => $sale->currency,
                'sold_at' => $sale->sold_at?->toIso8601String(),
                'flagged_anomaly' => $sale->flagged_anomaly,
            ],
        ], 201);
    }
}
