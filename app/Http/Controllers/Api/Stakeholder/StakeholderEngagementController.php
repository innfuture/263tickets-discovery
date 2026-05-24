<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Stakeholder;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\StakeholderApplication;
use App\Models\StakeholderDeliverable;
use App\Models\StakeholderInvitation;
use App\Services\Stakeholders\StakeholderEngagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Stakeholder-side lifecycle actions.
 *
 *   POST /api/v1/stakeholder/invitations/{uuid}/accept
 *   POST /api/v1/stakeholder/invitations/{uuid}/decline
 *   POST /api/v1/stakeholder/applications              (apply to event)
 *   POST /api/v1/stakeholder/applications/{uuid}/withdraw
 *   POST /api/v1/stakeholder/engagements/{uuid}/deliverables/{deliverable}/submit
 */
class StakeholderEngagementController extends Controller
{
    public function __construct(protected StakeholderEngagementService $engagements) {}

    public function acceptInvitation(Request $request, string $uuid): JsonResponse
    {
        $stakeholder = $request->attributes->get('stakeholder');
        $invitation = StakeholderInvitation::query()
            ->where('stakeholder_id', $stakeholder->id)
            ->where('uuid', $uuid)
            ->first();
        if (! $invitation) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $terms = $request->validate([
            'counter_terms' => ['nullable', 'array'],
        ]);

        try {
            $engagement = $this->engagements->fromInvitation(
                $invitation,
                (array) ($terms['counter_terms'] ?? []),
            );
        } catch (RuntimeException $e) {
            return response()->json(['error' => 'cannot_accept', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['engagement_uuid' => $engagement->uuid]]);
    }

    public function declineInvitation(Request $request, string $uuid): JsonResponse
    {
        $stakeholder = $request->attributes->get('stakeholder');
        $invitation = StakeholderInvitation::query()
            ->where('stakeholder_id', $stakeholder->id)
            ->where('uuid', $uuid)
            ->first();
        if (! $invitation) {
            return response()->json(['error' => 'not_found'], 404);
        }
        $invitation->forceFill([
            'status' => StakeholderInvitation::STATUS_DECLINED,
            'responded_at' => now(),
        ])->save();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function apply(Request $request): JsonResponse
    {
        $stakeholder = $request->attributes->get('stakeholder');
        if (! $stakeholder->canAcceptNewWork()) {
            return response()->json(['error' => 'account_not_verified'], 403);
        }

        $validated = $request->validate([
            'event_slug' => ['required', 'string'],
            'engagement_type' => ['required', 'string'],
            'pitch' => ['nullable', 'string', 'max:5000'],
            'proposed_terms' => ['nullable', 'array'],
        ]);

        $event = Event::query()->where('slug', $validated['event_slug'])->first();
        if (! $event) {
            return response()->json(['error' => 'event_not_found'], 404);
        }

        $application = StakeholderApplication::create([
            'event_id' => $event->id,
            'stakeholder_id' => $stakeholder->id,
            'engagement_type' => $validated['engagement_type'],
            'pitch' => $validated['pitch'] ?? null,
            'proposed_terms' => $validated['proposed_terms'] ?? [],
            'status' => StakeholderApplication::STATUS_SUBMITTED,
        ]);

        return response()->json(['data' => ['uuid' => $application->uuid, 'status' => $application->status]], 201);
    }

    public function withdrawApplication(Request $request, string $uuid): JsonResponse
    {
        $stakeholder = $request->attributes->get('stakeholder');
        $application = StakeholderApplication::query()
            ->where('stakeholder_id', $stakeholder->id)
            ->where('uuid', $uuid)
            ->first();
        if (! $application) {
            return response()->json(['error' => 'not_found'], 404);
        }
        $application->forceFill([
            'status' => StakeholderApplication::STATUS_WITHDRAWN,
            'responded_at' => now(),
        ])->save();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function submitDeliverable(Request $request, string $engagementUuid, string $deliverableUuid): JsonResponse
    {
        $stakeholder = $request->attributes->get('stakeholder');
        $deliverable = StakeholderDeliverable::query()
            ->whereHas('engagement', fn ($q) => $q->where('stakeholder_id', $stakeholder->id)->where('uuid', $engagementUuid))
            ->where('uuid', $deliverableUuid)
            ->first();
        if (! $deliverable) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $validated = $request->validate([
            'submission_notes' => ['nullable', 'string', 'max:5000'],
            'submission_attachments' => ['nullable', 'array'],
            'submission_attachments.*.url' => ['required_with:submission_attachments', 'url'],
            'submission_attachments.*.label' => ['nullable', 'string', 'max:191'],
        ]);

        $deliverable->forceFill([
            'status' => StakeholderDeliverable::STATUS_SUBMITTED,
            'submission_notes' => $validated['submission_notes'] ?? null,
            'submission_attachments' => $validated['submission_attachments'] ?? null,
            'submitted_at' => now(),
        ])->save();

        return response()->json(['data' => $deliverable->fresh()]);
    }
}
