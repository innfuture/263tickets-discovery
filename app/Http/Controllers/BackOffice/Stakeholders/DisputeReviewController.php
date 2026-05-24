<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Stakeholders;

use App\Http\Controllers\Controller;
use App\Models\EngagementDispute;
use App\Models\EventStakeholderEngagement;
use App\Models\Organization;
use App\Services\Stakeholders\DisputeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Organizer-side dispute review.
 *
 *   GET   /api/back-office/disputes
 *   POST  /api/back-office/engagements/{engagement}/disputes      open from org side
 *   POST  /api/back-office/disputes/{uuid}/resolve                 verdict
 */
class DisputeReviewController extends Controller
{
    public function __construct(protected DisputeService $disputes) {}

    public function index(Organization $currentOrganization, Request $request): JsonResponse
    {
        return response()->json([
            'data' => EngagementDispute::query()
                ->whereHas('engagement.event', fn ($q) => $q->where('organisation_id', $currentOrganization->uuid))
                ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
                ->with('engagement.stakeholder:id,name,company,type', 'engagement.event:id,name,slug')
                ->orderByDesc('created_at')
                ->limit(200)
                ->get(),
        ]);
    }

    public function openFromOrg(Organization $currentOrganization, EventStakeholderEngagement $engagement, Request $request): JsonResponse
    {
        abort_if($engagement->event?->organisation_id !== $currentOrganization->uuid, 404);

        $validated = $request->validate([
            'reason_code' => ['required', 'in:non_delivery,payment_dispute,quality_issue,breach,other'],
            'statement' => ['required', 'string', 'max:5000'],
            'evidence' => ['nullable', 'array'],
        ]);

        try {
            $dispute = $this->disputes->open(
                engagement: $engagement,
                raisedByType: EngagementDispute::RAISED_BY_ORGANIZER,
                raisedById: $request->user()->id,
                reasonCode: $validated['reason_code'],
                statement: $validated['statement'],
                evidence: $validated['evidence'] ?? [],
            );
        } catch (RuntimeException $e) {
            return response()->json(['error' => 'dispute_failed', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['uuid' => $dispute->uuid]], 201);
    }

    public function resolve(Organization $currentOrganization, EngagementDispute $dispute, Request $request): JsonResponse
    {
        abort_if($dispute->engagement?->event?->organisation_id !== $currentOrganization->uuid, 404);

        $validated = $request->validate([
            'resolution' => ['required', 'in:upheld,partial,rejected'],
            'notes' => ['required', 'string', 'max:5000'],
        ]);

        try {
            $this->disputes->resolve(
                dispute: $dispute,
                resolution: $validated['resolution'],
                notes: $validated['notes'],
                reviewer: $request->user(),
            );
        } catch (RuntimeException $e) {
            return response()->json(['error' => 'resolve_failed', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['status' => $dispute->fresh()->status, 'resolution' => $dispute->resolution]]);
    }
}
