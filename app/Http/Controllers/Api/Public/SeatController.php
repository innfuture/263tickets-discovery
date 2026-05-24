<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\CheckoutSession;
use App\Models\Event;
use App\Models\Seat;
use App\Models\SeatMap;
use App\Services\Storefront\CheckoutSessionManager;
use App\Services\Storefront\Exceptions\SeatUnavailableException;
use App\Services\Storefront\PriceCalculator;
use App\Services\Storefront\SeatedReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reserved-seating storefront endpoints.
 *
 *   GET   /api/v1/public/events/{slug}/seats    — layout + per-seat availability
 *   PATCH /api/v1/public/checkout/sessions/{uuid}/seats   — hold/release seats
 */
class SeatController extends Controller
{
    public function __construct(
        protected SeatedReservation $seats,
        protected CheckoutSessionManager $sessions,
        protected PriceCalculator $prices,
    ) {}

    public function index(string $slug): JsonResponse
    {
        $event = Event::query()->where('slug', $slug)->first();
        if (! $event) {
            return response()->json(['error' => 'event_not_found'], 404);
        }

        $map = SeatMap::query()
            ->where('event_id', $event->id)
            ->with('seats.activeHold')
            ->first();

        if (! $map) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'uuid' => $map->uuid,
                'name' => $map->name,
                'layout' => $map->layout,
                'zones' => $map->zones,
                'seats' => $map->seats->map(fn ($seat) => [
                    'uuid' => $seat->uuid,
                    'zone' => $seat->zone_code,
                    'row' => $seat->row_label,
                    'label' => $seat->seat_label,
                    'x' => $seat->x,
                    'y' => $seat->y,
                    'status' => $this->effectiveStatus($seat),
                    'ticket_category_id' => $seat->ticket_category_id,
                    'attributes' => $seat->attributes ?? [],
                ])->all(),
            ],
        ]);
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        $session = CheckoutSession::query()->where('uuid', $uuid)->first();
        if (! $session) {
            return response()->json(['error' => 'session_not_found'], 404);
        }
        if (! $session->isMutable()) {
            return response()->json(['error' => 'session_locked'], 409);
        }

        $validated = $request->validate([
            'seat_uuids' => ['required', 'array'],
            'seat_uuids.*' => ['uuid'],
        ]);

        try {
            $this->seats->hold($session, $validated['seat_uuids']);
        } catch (SeatUnavailableException $e) {
            return response()->json([
                'error' => 'seat_unavailable',
                'seat_uuid' => $e->seatUuid,
                'reason' => $e->reason,
            ], 409);
        }

        $this->sessions->touch($session);
        $this->prices->recompute($session->refresh());

        return response()->json([
            'data' => [
                'session_uuid' => $session->uuid,
                'expires_at' => optional($session->expires_at)->toIso8601String(),
                'totals' => [
                    'subtotal_cents' => (int) $session->subtotal_cents,
                    'total_cents' => (int) $session->total_cents,
                ],
            ],
        ]);
    }

    /**
     * Map the persisted seat status into what the public renderer
     * should show. A held-by-someone-else seat is rendered as `held`;
     * a seat whose hold has expired but hasn't been swept yet is
     * still effectively available.
     */
    protected function effectiveStatus(Seat $seat): string
    {
        if ($seat->status === Seat::STATUS_SOLD || $seat->status === Seat::STATUS_BLOCKED) {
            return $seat->status;
        }
        $hold = $seat->activeHold;
        if ($hold && $hold->expires_at && $hold->expires_at->isFuture()) {
            return Seat::STATUS_HELD;
        }

        return Seat::STATUS_AVAILABLE;
    }
}
