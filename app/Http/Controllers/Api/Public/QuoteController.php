<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Storefront\QuoteRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Group / corporate quote intake.
 *
 *   POST /api/v1/public/events/{slug}/quote
 */
class QuoteController extends Controller
{
    public function __construct(protected QuoteRequestService $quotes) {}

    public function store(Request $request, string $slug): JsonResponse
    {
        $event = Event::query()->where('slug', $slug)->first();
        if (! $event) {
            return response()->json(['error' => 'event_not_found'], 404);
        }

        $validated = $request->validate([
            'contact_name' => ['required', 'string', 'max:191'],
            'contact_email' => ['required', 'email', 'max:191'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'company_name' => ['nullable', 'string', 'max:191'],
            'quantity_requested' => ['required', 'integer', 'min:1', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'ticket_category_id' => ['nullable', 'integer'],
        ]);

        $quote = $this->quotes->submit($event, $validated, $request);

        return response()->json([
            'data' => [
                'uuid' => $quote->uuid,
                'status' => $quote->status,
            ],
        ], 201);
    }
}
