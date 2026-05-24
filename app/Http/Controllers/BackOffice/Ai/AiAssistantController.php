<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Ai;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organization;
use App\Models\RefundRequest;
use App\Services\Ai\Contracts\AiAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Organizer-side AI panel. Three POST endpoints, one per task. All
 * are idempotent → fire as many times as the operator wants. The
 * dashboard panel renders the response + "use this" buttons.
 *
 *   POST /api/back-office/ai/event-copy        { event_slug, tone? }
 *   POST /api/back-office/ai/refund-reply      { refund_request_uuid }
 *   POST /api/back-office/ai/sales-insight     { event_slug }
 */
class AiAssistantController extends Controller
{
    public function __construct(protected AiAssistant $assistant) {}

    public function eventCopy(Organization $currentOrganization, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_slug' => ['required', 'string'],
            'tone' => ['nullable', 'in:punchy,formal,playful,minimal'],
        ]);
        $event = Event::query()
            ->where('slug', $validated['event_slug'])
            ->where('organisation_id', $currentOrganization->uuid)
            ->first();
        if (! $event) {
            return response()->json(['error' => 'event_not_found'], 404);
        }

        $result = $this->assistant->generateEventCopy($event, ['tone' => $validated['tone'] ?? 'punchy']);
        if (! $result) {
            return response()->json(['error' => 'assistant_unavailable'], 503);
        }

        return response()->json([
            'data' => $result + ['assistant' => $this->assistant->identifier()],
        ]);
    }

    public function refundReply(Organization $currentOrganization, Request $request): JsonResponse
    {
        $validated = $request->validate(['refund_request_uuid' => ['required', 'uuid']]);
        $refund = RefundRequest::query()
            ->where('uuid', $validated['refund_request_uuid'])
            ->where('organisation_id', $currentOrganization->uuid)
            ->with('order.event')
            ->first();
        if (! $refund) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $result = $this->assistant->draftRefundReply($refund, $refund->order);
        if (! $result) {
            return response()->json(['error' => 'assistant_unavailable'], 503);
        }

        return response()->json(['data' => $result + ['assistant' => $this->assistant->identifier()]]);
    }

    public function salesInsight(Organization $currentOrganization, Request $request): JsonResponse
    {
        $validated = $request->validate(['event_slug' => ['required', 'string']]);
        $event = Event::query()
            ->where('slug', $validated['event_slug'])
            ->where('organisation_id', $currentOrganization->uuid)
            ->first();
        if (! $event) {
            return response()->json(['error' => 'event_not_found'], 404);
        }

        $result = $this->assistant->generateSalesInsight($event);
        if (! $result) {
            return response()->json(['error' => 'assistant_unavailable'], 503);
        }

        return response()->json(['data' => $result + ['assistant' => $this->assistant->identifier()]]);
    }
}
