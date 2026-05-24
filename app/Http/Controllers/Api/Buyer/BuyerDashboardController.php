<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Models\BuyerFavorite;
use App\Models\BuyerNotification;
use App\Models\Event;
use App\Models\Order;
use App\Services\Buyers\BuyerNotificationService;
use App\Services\Storefront\Search\Contracts\RecommendationStrategy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * All the GET-side endpoints under /api/v1/buyer/* — the dashboard
 * read surface. POSTs / DELETEs live in the Auth + SelfService
 * controllers.
 *
 *   GET /me
 *   GET /upcoming
 *   GET /past
 *   GET /orders
 *   GET /orders/{reference}
 *   GET /favorites
 *   POST /favorites/{slug}
 *   DELETE /favorites/{slug}
 *   GET /notifications
 *   POST /notifications/{uuid}/read
 *   POST /notifications/read-all
 *   GET /recommendations
 */
class BuyerDashboardController extends Controller
{
    public function __construct(
        protected BuyerNotificationService $notifications,
        protected RecommendationStrategy $recommend,
    ) {}

    public function me(Request $request): JsonResponse
    {
        $buyer = $request->attributes->get('buyer');

        return response()->json(['data' => [
            'uuid' => $buyer->uuid,
            'email' => $buyer->email,
            'name' => $buyer->name,
            'phone' => $buyer->phone,
            'country_code' => $buyer->country_code,
            'locale' => $buyer->locale,
            'notifications_email' => (bool) $buyer->notifications_email,
            'notifications_sms' => (bool) $buyer->notifications_sms,
            'marketing_opt_in' => (bool) $buyer->marketing_opt_in,
            'email_verified_at' => optional($buyer->email_verified_at)->toIso8601String(),
        ]]);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $buyer = $request->attributes->get('buyer');
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:40'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'locale' => ['nullable', 'string', 'max:10'],
            'notifications_email' => ['nullable', 'boolean'],
            'notifications_sms' => ['nullable', 'boolean'],
            'marketing_opt_in' => ['nullable', 'boolean'],
        ]);
        $buyer->fill(array_filter($validated, fn ($v) => $v !== null))->save();

        return $this->me($request);
    }

    public function upcoming(Request $request): JsonResponse
    {
        $buyer = $request->attributes->get('buyer');
        $rows = Order::query()
            ->where('buyer_id', $buyer->id)
            ->whereHas('event', fn ($q) => $q->where('starts_at', '>=', now()))
            ->with('event:id,name,slug,starts_at,venue_name,city,banner_image_path')
            ->orderBy('placed_at', 'desc')
            ->get();

        return response()->json(['data' => $rows->map(fn ($o) => $this->orderSummary($o))->all()]);
    }

    public function past(Request $request): JsonResponse
    {
        $buyer = $request->attributes->get('buyer');
        $rows = Order::query()
            ->where('buyer_id', $buyer->id)
            ->whereHas('event', fn ($q) => $q->where('starts_at', '<', now()))
            ->with('event:id,name,slug,starts_at,venue_name,city,banner_image_path')
            ->orderBy('placed_at', 'desc')
            ->limit(200)
            ->get();

        return response()->json(['data' => $rows->map(fn ($o) => $this->orderSummary($o))->all()]);
    }

    public function orders(Request $request): JsonResponse
    {
        $buyer = $request->attributes->get('buyer');
        $rows = Order::query()
            ->where('buyer_id', $buyer->id)
            ->with('event:id,name,slug,starts_at')
            ->orderBy('placed_at', 'desc')
            ->limit(200)
            ->get();

        return response()->json(['data' => $rows->map(fn ($o) => $this->orderSummary($o))->all()]);
    }

    public function order(Request $request, string $reference): JsonResponse
    {
        $buyer = $request->attributes->get('buyer');
        $order = Order::query()
            ->where('buyer_id', $buyer->id)
            ->where('reference', $reference)
            ->with('items.category', 'event')
            ->first();
        if (! $order) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json(['data' => $this->orderDetail($order)]);
    }

    public function favorites(Request $request): JsonResponse
    {
        $buyer = $request->attributes->get('buyer');
        $rows = BuyerFavorite::query()
            ->where('buyer_id', $buyer->id)
            ->with('event:id,name,slug,starts_at,city,banner_image_path')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $rows->pluck('event')->filter()->values()]);
    }

    public function addFavorite(Request $request, string $slug): JsonResponse
    {
        $buyer = $request->attributes->get('buyer');
        $event = Event::query()->where('slug', $slug)->first();
        if (! $event) {
            return response()->json(['error' => 'event_not_found'], 404);
        }
        BuyerFavorite::firstOrCreate(['buyer_id' => $buyer->id, 'event_id' => $event->id]);

        return response()->json(['data' => ['ok' => true]]);
    }

    public function removeFavorite(Request $request, string $slug): JsonResponse
    {
        $buyer = $request->attributes->get('buyer');
        $event = Event::query()->where('slug', $slug)->first();
        if (! $event) {
            return response()->json(['error' => 'event_not_found'], 404);
        }
        BuyerFavorite::query()
            ->where('buyer_id', $buyer->id)
            ->where('event_id', $event->id)
            ->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $buyer = $request->attributes->get('buyer');
        $rows = BuyerNotification::query()
            ->where('buyer_id', $buyer->id)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'unread_count' => $rows->whereNull('read_at')->count(),
            ],
        ]);
    }

    public function readNotification(Request $request, string $uuid): JsonResponse
    {
        $buyer = $request->attributes->get('buyer');
        $row = BuyerNotification::query()
            ->where('buyer_id', $buyer->id)
            ->where('uuid', $uuid)
            ->first();
        if (! $row) {
            return response()->json(['error' => 'not_found'], 404);
        }
        $this->notifications->markRead($row);

        return response()->json(['data' => ['ok' => true]]);
    }

    public function readAllNotifications(Request $request): JsonResponse
    {
        $buyer = $request->attributes->get('buyer');
        $n = $this->notifications->markAllRead($buyer);

        return response()->json(['data' => ['marked' => $n]]);
    }

    public function recommendations(Request $request): JsonResponse
    {
        $buyer = $request->attributes->get('buyer');

        // Strategy: take the most recent attended event + return
        // related upcoming events. Falls back to featured if there's
        // no history yet.
        $lastOrder = Order::query()
            ->where('buyer_id', $buyer->id)
            ->with('event')
            ->orderByDesc('placed_at')
            ->first();

        if ($lastOrder?->event) {
            return response()->json([
                'data' => $this->recommend->for($lastOrder->event, 8)
                    ->map(fn ($e) => [
                        'slug' => $e->slug,
                        'name' => $e->name,
                        'starts_at' => optional($e->starts_at)->toIso8601String(),
                        'city' => $e->city,
                        'banner_image_path' => $e->banner_image_path,
                    ])->all(),
            ]);
        }

        // No history → return public featured.
        return response()->json([
            'data' => Event::query()
                ->where('is_featured', true)
                ->where('starts_at', '>=', now())
                ->limit(8)
                ->get(['slug', 'name', 'starts_at', 'city', 'banner_image_path']),
        ]);
    }

    /** @return array<string, mixed> */
    protected function orderSummary(Order $order): array
    {
        return [
            'reference' => $order->reference,
            'uuid' => $order->uuid,
            'status' => $order->status,
            'placed_at' => optional($order->placed_at)->toIso8601String(),
            'currency' => $order->currency,
            'total_cents' => (int) $order->total_cents,
            'event' => $order->event ? [
                'slug' => $order->event->slug,
                'name' => $order->event->name,
                'starts_at' => optional($order->event->starts_at)->toIso8601String(),
                'venue_name' => $order->event->venue_name,
                'city' => $order->event->city,
                'banner_image_path' => $order->event->banner_image_path,
            ] : null,
        ];
    }

    /** @return array<string, mixed> */
    protected function orderDetail(Order $order): array
    {
        return array_merge($this->orderSummary($order), [
            'items' => $order->items->map(fn ($i) => [
                'id' => (int) $i->id,
                'attendee_name' => $i->attendee_name,
                'ticket_type' => $i->ticket_type,
                'unit_price_cents' => (int) $i->unit_price_cents,
                'qr_payload' => $i->qr_payload,
            ])->all(),
        ]);
    }
}
