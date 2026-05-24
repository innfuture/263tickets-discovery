<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\CheckoutSession;
use App\Models\Event;
use App\Models\EventAddon;
use App\Services\Storefront\AddonManager;
use App\Services\Storefront\CheckoutSessionManager;
use App\Services\Storefront\PriceCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 *   GET   /api/v1/public/events/{slug}/addons         — list visible addons
 *   PATCH /api/v1/public/checkout/sessions/{uuid}/addons — set quantities
 */
class AddonController extends Controller
{
    public function __construct(
        protected AddonManager $addons,
        protected PriceCalculator $prices,
        protected CheckoutSessionManager $sessions,
    ) {}

    public function index(string $slug): JsonResponse
    {
        $event = Event::query()->where('slug', $slug)->first();
        if (! $event) {
            return response()->json(['error' => 'event_not_found'], 404);
        }

        $addons = EventAddon::query()
            ->where('event_id', $event->id)
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $addons->map(fn (EventAddon $a) => [
                'uuid' => $a->uuid,
                'name' => $a->name,
                'description' => $a->description,
                'price_cents' => (int) $a->price_cents,
                'currency' => $a->currency,
                'remaining_stock' => $a->remainingStock(),
                'min_per_order' => (int) $a->min_per_order,
                'max_per_order' => (int) $a->max_per_order,
                'requires_ticket' => (bool) $a->requires_ticket,
                'image_path' => $a->image_path,
            ])->all(),
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
            'addons' => ['required', 'array'],
            'addons.*.event_addon_uuid' => ['required', 'uuid'],
            'addons.*.quantity' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        try {
            $this->addons->sync($session, $validated['addons']);
        } catch (RuntimeException $e) {
            return response()->json(['error' => 'addon_unavailable', 'message' => $e->getMessage()], 422);
        }

        $this->sessions->touch($session);
        $this->prices->recompute($session->refresh());

        return response()->json([
            'data' => [
                'addons' => $session->refresh()->load('addons.addon')->addons->map(fn ($r) => [
                    'event_addon_uuid' => $r->addon?->uuid,
                    'name' => $r->addon?->name,
                    'quantity' => (int) $r->quantity,
                    'line_total_cents' => (int) $r->line_total_cents,
                ])->all(),
                'totals' => [
                    'subtotal_cents' => (int) $session->subtotal_cents,
                    'total_cents' => (int) $session->total_cents,
                ],
            ],
        ]);
    }
}
