<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Services\Storefront\QuoteRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Organizer-side quote pipeline. Three actions:
 *
 *   GET   /quotes                     list pending / in-review
 *   PATCH /quotes/{uuid}/respond      attach a price; status → quoted
 *   POST  /quotes/{uuid}/convert      create a pre-filled checkout
 *                                      session for the contact email
 *                                      and return the storefront URL
 *                                      to share with them.
 */
class QuoteConversionController extends Controller
{
    public function __construct(protected QuoteRequestService $quotes) {}

    public function index(Organization $currentOrganization, Request $request): JsonResponse
    {
        return response()->json([
            'data' => QuoteRequest::query()
                ->where('organisation_id', $currentOrganization->uuid)
                ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
                ->with('event:id,name,slug,starts_at')
                ->orderByDesc('created_at')
                ->limit(200)
                ->get(),
        ]);
    }

    public function respond(Organization $currentOrganization, QuoteRequest $quote, Request $request): JsonResponse
    {
        abort_if($quote->organisation_id !== $currentOrganization->uuid, 404);

        $validated = $request->validate([
            'quoted_unit_price_cents' => ['required', 'integer', 'min:0'],
            'quoted_currency' => ['required', 'string', 'size:3'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $quote->forceFill([
            'status' => QuoteRequest::STATUS_QUOTED,
            'quoted_unit_price_cents' => $validated['quoted_unit_price_cents'],
            'quoted_currency' => strtoupper($validated['quoted_currency']),
            'expires_at' => $validated['expires_at'] ?? null,
            'responded_at' => now(),
        ])->save();

        return response()->json(['data' => $quote->fresh()]);
    }

    public function convert(Organization $currentOrganization, QuoteRequest $quote, Request $request): JsonResponse
    {
        abort_if($quote->organisation_id !== $currentOrganization->uuid, 404);
        abort_if($quote->status !== QuoteRequest::STATUS_QUOTED, 422, 'Quote must be in `quoted` status before converting.');

        $session = $this->quotes->convertToCheckout($quote, $request);

        $publicUrl = rtrim((string) config('storefront.public_url'), '/')
            .'/checkout/'.$session->uuid;

        return response()->json(['data' => [
            'checkout_session_uuid' => $session->uuid,
            'public_url' => $publicUrl,
            'expires_at' => optional($session->expires_at)->toIso8601String(),
        ]]);
    }
}
