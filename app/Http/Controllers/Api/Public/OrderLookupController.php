<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Buyer-side order retrieval — no account needed.
 *
 *   POST /api/v1/public/orders/lookup   { reference, email }
 *
 * Verifies the email matches the order's `buyer_email` (constant-time
 * compare via hash_equals) to prevent enumeration of references.
 *
 * On success returns a short-lived signed URL the front-end can use
 * to render the receipt + tickets.
 */
class OrderLookupController extends Controller
{
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:191'],
        ]);

        $order = Order::query()
            ->with('items.category', 'event')
            ->where('reference', $validated['reference'])
            ->first();

        // Deliberately return the same response shape for both
        // "wrong reference" and "right reference + wrong email" so
        // we don't leak which references exist.
        if (! $order || ! hash_equals(
            strtolower((string) $order->buyer_email),
            strtolower($validated['email']),
        )) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json(['data' => $this->payload($order)]);
    }

    public function show(Request $request, string $reference): JsonResponse
    {
        // Mailable-issued links arrive signed; bare-reference access is
        // refused so a leaked reference alone doesn't grant access.
        if (! $request->hasValidSignature()) {
            return response()->json(['error' => 'signature_invalid'], 403);
        }

        $order = Order::query()
            ->with('items.category', 'event')
            ->where('reference', $reference)
            ->first();
        if (! $order) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json(['data' => $this->payload($order, signed: true)]);
    }

    /** @return array<string, mixed> */
    protected function payload(Order $order, bool $signed = false): array
    {
        $passLinks = [];
        if ($signed) {
            $expiry = now()->addMinutes((int) config('storefront.fulfilment.signed_link_expiry_minutes', 60 * 24 * 7));
            foreach ($order->items as $item) {
                $passLinks[$item->id] = [
                    'pdf' => URL::temporarySignedRoute('public.orders.pass', $expiry, [
                        'reference' => $order->reference,
                        'item' => $item->id,
                        'provider' => 'pdf',
                    ]),
                    'apple' => URL::temporarySignedRoute('public.orders.pass', $expiry, [
                        'reference' => $order->reference,
                        'item' => $item->id,
                        'provider' => 'apple',
                    ]),
                    'google' => URL::temporarySignedRoute('public.orders.pass', $expiry, [
                        'reference' => $order->reference,
                        'item' => $item->id,
                        'provider' => 'google',
                    ]),
                ];
            }
        }

        return [
            'reference' => $order->reference,
            'uuid' => $order->uuid,
            'status' => $order->status,
            'placed_at' => optional($order->placed_at)->toIso8601String(),
            'currency' => $order->currency,
            'totals' => [
                'subtotal_cents' => (int) $order->subtotal_cents,
                'discount_cents' => (int) ($order->discount_cents ?? 0),
                'tax_cents' => (int) $order->tax_cents,
                'fee_cents' => (int) $order->fee_cents,
                'total_cents' => (int) $order->total_cents,
            ],
            'buyer' => [
                'name' => $order->buyer_name,
                'email' => $order->buyer_email,
            ],
            'event' => $order->event ? [
                'slug' => $order->event->slug,
                'name' => $order->event->name,
                'starts_at' => optional($order->event->starts_at)->toIso8601String(),
                'venue_name' => $order->event->venue_name,
                'city' => $order->event->city,
                'country_code' => $order->event->country_code,
            ] : null,
            'items' => $order->items->map(fn ($item) => [
                'id' => (int) $item->id,
                'attendee_name' => $item->attendee_name,
                'attendee_email' => $item->attendee_email,
                'ticket_type' => $item->ticket_type,
                'unit_price_cents' => (int) $item->unit_price_cents,
                'qr_payload' => $item->qr_payload,
                'pass_urls' => $passLinks[$item->id] ?? null,
            ])->all(),
        ];
    }
}
