<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Stakeholder;

use App\Http\Controllers\Controller;
use App\Models\EngagementDispute;
use App\Models\EventStakeholderEngagement;
use App\Services\Stakeholders\DisputeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Stakeholder-side dispute actions.
 *
 *   GET   /api/v1/stakeholder/disputes
 *   POST  /api/v1/stakeholder/engagements/{uuid}/disputes
 *   POST  /api/v1/stakeholder/disputes/{uuid}/evidence
 *   POST  /api/v1/stakeholder/disputes/{uuid}/withdraw
 */
class StakeholderDisputeController extends Controller
{
    public function __construct(protected DisputeService $disputes) {}

    public function index(Request $request): JsonResponse
    {
        $stakeholder = $request->attributes->get('stakeholder');

        return response()->json([
            'data' => EngagementDispute::query()
                ->whereHas('engagement', fn ($q) => $q->where('stakeholder_id', $stakeholder->id))
                ->with('engagement:id,uuid,event_id,stakeholder_id', 'engagement.event:id,name,slug')
                ->orderByDesc('created_at')
                ->limit(100)
                ->get(),
        ]);
    }

    public function open(Request $request, string $engagementUuid): JsonResponse
    {
        $stakeholder = $request->attributes->get('stakeholder');
        $engagement = EventStakeholderEngagement::query()
            ->where('stakeholder_id', $stakeholder->id)
            ->where('uuid', $engagementUuid)
            ->first();
        if (! $engagement) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $validated = $request->validate([
            'reason_code' => ['required', 'in:non_delivery,payment_dispute,quality_issue,breach,other'],
            'statement' => ['required', 'string', 'max:5000'],
            'evidence' => ['nullable', 'array'],
        ]);

        try {
            $dispute = $this->disputes->open(
                engagement: $engagement,
                raisedByType: EngagementDispute::RAISED_BY_STAKEHOLDER,
                raisedById: $stakeholder->id,
                reasonCode: $validated['reason_code'],
                statement: $validated['statement'],
                evidence: $validated['evidence'] ?? [],
            );
        } catch (RuntimeException $e) {
            return response()->json(['error' => 'dispute_failed', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['uuid' => $dispute->uuid, 'status' => $dispute->status]], 201);
    }

    public function appendEvidence(Request $request, string $uuid): JsonResponse
    {
        $stakeholder = $request->attributes->get('stakeholder');
        $dispute = EngagementDispute::query()
            ->whereHas('engagement', fn ($q) => $q->where('stakeholder_id', $stakeholder->id))
            ->where('uuid', $uuid)
            ->first();
        if (! $dispute) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $validated = $request->validate(['evidence' => ['required', 'array']]);
        $this->disputes->appendEvidence($dispute, $validated['evidence']);

        return response()->json(['data' => ['ok' => true]]);
    }

    public function withdraw(Request $request, string $uuid): JsonResponse
    {
        $stakeholder = $request->attributes->get('stakeholder');
        $dispute = EngagementDispute::query()
            ->whereHas('engagement', fn ($q) => $q->where('stakeholder_id', $stakeholder->id))
            ->where('uuid', $uuid)
            ->first();
        if (! $dispute) {
            return response()->json(['error' => 'not_found'], 404);
        }
        try {
            $this->disputes->withdraw($dispute, EngagementDispute::RAISED_BY_STAKEHOLDER, $stakeholder->id);
        } catch (RuntimeException $e) {
            return response()->json(['error' => 'withdraw_failed', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['ok' => true]]);
    }
}
