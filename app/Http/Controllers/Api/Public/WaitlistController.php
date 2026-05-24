<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TicketCategory;
use App\Services\Storefront\WaitlistManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public waitlist enrolment for sold-out events / tiers.
 *
 *   POST /api/v1/public/events/{slug}/waitlist
 *
 * Returns the entry uuid so the front-end can show "you're on the
 * list" without a follow-up call.
 */
class WaitlistController extends Controller
{
    public function __construct(protected WaitlistManager $waitlist) {}

    public function store(Request $request, string $slug): JsonResponse
    {
        $event = Event::query()->where('slug', $slug)->first();
        if (! $event) {
            return response()->json(['error' => 'event_not_found'], 404);
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:191'],
            'name' => ['nullable', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:40'],
            'ticket_category_uuid' => ['nullable', 'uuid'],
            'quantity_requested' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $category = null;
        if (! empty($validated['ticket_category_uuid'])) {
            $category = TicketCategory::query()
                ->where('uuid', $validated['ticket_category_uuid'])
                ->where('event_id', $event->id)
                ->first();
        }

        $entry = $this->waitlist->enroll(
            event: $event,
            email: $validated['email'],
            name: $validated['name'] ?? null,
            phone: $validated['phone'] ?? null,
            category: $category,
            quantityRequested: (int) ($validated['quantity_requested'] ?? 1),
        );

        return response()->json([
            'data' => [
                'uuid' => $entry->uuid,
                'status' => $entry->status?->value,
                'event_slug' => $event->slug,
                'ticket_category_uuid' => $category?->uuid,
            ],
        ], 201);
    }
}
