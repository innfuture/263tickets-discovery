<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Stakeholders;

use App\Http\Controllers\Controller;
use App\Models\EventStakeholderEngagement;
use App\Models\Organization;
use App\Models\Stakeholder;
use App\Models\StakeholderApplication;
use App\Models\StakeholderDeliverable;
use App\Models\StakeholderInvitation;
use App\Models\StakeholderPayment;
use App\Models\StakeholderReview;
use App\Services\Stakeholders\StakeholderEngagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Organizer-side stakeholder management. Surfaces inside the
 * `{current_organization}` group:
 *
 *   GET   /api/back-office/stakeholders                 list verified marketplace
 *   POST  /api/back-office/events/{event}/invitations   invite a stakeholder
 *   GET   /api/back-office/events/{event}/applications  inbox of applications
 *   POST  /api/back-office/applications/{uuid}/approve  approve + create engagement
 *   POST  /api/back-office/applications/{uuid}/decline
 *   GET   /api/back-office/engagements                  active engagements across org
 *   POST  /api/back-office/engagements/{uuid}/complete
 *   POST  /api/back-office/engagements/{uuid}/cancel
 *   POST  /api/back-office/deliverables/{uuid}/approve
 *   POST  /api/back-office/deliverables/{uuid}/reject
 *   POST  /api/back-office/payments/{uuid}/mark-paid
 *   POST  /api/back-office/engagements/{uuid}/reviews   leave review
 */
class StakeholderEngagementBackOfficeController extends Controller
{
    public function __construct(protected StakeholderEngagementService $engagements) {}

    public function stakeholders(Request $request): JsonResponse
    {
        $rows = Stakeholder::query()
            ->where('status', Stakeholder::STATUS_VERIFIED)
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->with('profile:id,stakeholder_id,logo_path,tags,service_areas')
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'uuid', 'type', 'name', 'company', 'country_code']);

        return response()->json(['data' => $rows]);
    }

    public function invite(Organization $currentOrganization, \App\Models\Event $event, Request $request): JsonResponse
    {
        abort_if($event->organisation_id !== $currentOrganization->uuid, 404);

        $validated = $request->validate([
            'stakeholder_uuid' => ['required', 'uuid'],
            'engagement_type' => ['required', 'string'],
            'message' => ['nullable', 'string', 'max:5000'],
            'proposed_terms' => ['nullable', 'array'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $stakeholder = Stakeholder::query()->where('uuid', $validated['stakeholder_uuid'])->first();
        if (! $stakeholder) {
            return response()->json(['error' => 'stakeholder_not_found'], 404);
        }

        $invitation = StakeholderInvitation::create([
            'event_id' => $event->id,
            'stakeholder_id' => $stakeholder->id,
            'invited_by_user_id' => $request->user()?->id,
            'engagement_type' => $validated['engagement_type'],
            'message' => $validated['message'] ?? null,
            'proposed_terms' => $validated['proposed_terms'] ?? [],
            'status' => StakeholderInvitation::STATUS_PENDING,
            'expires_at' => $validated['expires_at'] ?? now()->addDays(14),
        ]);

        return response()->json(['data' => ['uuid' => $invitation->uuid]], 201);
    }

    public function applications(Organization $currentOrganization, \App\Models\Event $event): JsonResponse
    {
        abort_if($event->organisation_id !== $currentOrganization->uuid, 404);

        return response()->json([
            'data' => StakeholderApplication::query()
                ->where('event_id', $event->id)
                ->with('stakeholder:id,uuid,name,company,type,status')
                ->orderByDesc('created_at')
                ->limit(200)
                ->get(),
        ]);
    }

    public function approveApplication(Organization $currentOrganization, StakeholderApplication $application, Request $request): JsonResponse
    {
        abort_if($application->event?->organisation_id !== $currentOrganization->uuid, 404);

        $terms = $request->validate(['final_terms' => ['nullable', 'array']]);

        try {
            $engagement = $this->engagements->fromApplication(
                $application,
                (array) ($terms['final_terms'] ?? []),
                $request->user()->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['error' => 'cannot_approve', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['engagement_uuid' => $engagement->uuid]]);
    }

    public function declineApplication(Organization $currentOrganization, StakeholderApplication $application, Request $request): JsonResponse
    {
        abort_if($application->event?->organisation_id !== $currentOrganization->uuid, 404);
        $validated = $request->validate(['review_notes' => ['nullable', 'string', 'max:2000']]);
        $application->forceFill([
            'status' => StakeholderApplication::STATUS_DECLINED,
            'responded_at' => now(),
            'reviewed_by_user_id' => $request->user()?->id,
            'review_notes' => $validated['review_notes'] ?? null,
        ])->save();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function engagements(Organization $currentOrganization, Request $request): JsonResponse
    {
        $rows = EventStakeholderEngagement::query()
            ->whereHas('event', fn ($q) => $q->where('organisation_id', $currentOrganization->uuid))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->with('event:id,name,slug,starts_at', 'stakeholder:id,uuid,name,company,type')
            ->orderByDesc('starts_at')
            ->limit(200)
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function completeEngagement(Organization $currentOrganization, EventStakeholderEngagement $engagement): JsonResponse
    {
        abort_if($engagement->event?->organisation_id !== $currentOrganization->uuid, 404);
        $this->engagements->complete($engagement);

        return response()->json(['data' => ['status' => $engagement->fresh()->status]]);
    }

    public function cancelEngagement(Organization $currentOrganization, EventStakeholderEngagement $engagement, Request $request): JsonResponse
    {
        abort_if($engagement->event?->organisation_id !== $currentOrganization->uuid, 404);
        $reason = (string) $request->input('reason');
        $this->engagements->cancel($engagement, $reason ?: null);

        return response()->json(['data' => ['status' => $engagement->fresh()->status]]);
    }

    public function approveDeliverable(Organization $currentOrganization, StakeholderDeliverable $deliverable, Request $request): JsonResponse
    {
        abort_if($deliverable->engagement?->event?->organisation_id !== $currentOrganization->uuid, 404);
        $deliverable->forceFill([
            'status' => StakeholderDeliverable::STATUS_APPROVED,
            'approved_by_user_id' => $request->user()?->id,
            'approved_at' => now(),
        ])->save();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function rejectDeliverable(Organization $currentOrganization, StakeholderDeliverable $deliverable, Request $request): JsonResponse
    {
        abort_if($deliverable->engagement?->event?->organisation_id !== $currentOrganization->uuid, 404);
        $validated = $request->validate(['review_notes' => ['nullable', 'string', 'max:2000']]);
        $deliverable->forceFill([
            'status' => StakeholderDeliverable::STATUS_REJECTED,
            'review_notes' => $validated['review_notes'] ?? null,
            'approved_by_user_id' => $request->user()?->id,
            'approved_at' => null,
        ])->save();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function markPaymentPaid(Organization $currentOrganization, StakeholderPayment $payment, Request $request): JsonResponse
    {
        abort_if($payment->engagement?->event?->organisation_id !== $currentOrganization->uuid, 404);
        $payment->forceFill([
            'status' => StakeholderPayment::STATUS_PAID,
            'paid_at' => now(),
        ])->save();

        return response()->json(['data' => ['status' => $payment->status]]);
    }

    public function leaveReview(Organization $currentOrganization, EventStakeholderEngagement $engagement, Request $request): JsonResponse
    {
        abort_if($engagement->event?->organisation_id !== $currentOrganization->uuid, 404);

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'criteria_ratings' => ['nullable', 'array'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        $review = StakeholderReview::updateOrCreate(
            [
                'event_stakeholder_engagement_id' => $engagement->id,
                'direction' => StakeholderReview::DIRECTION_ORG_TO_STAKEHOLDER,
            ],
            $validated + ['author_user_id' => $request->user()?->id],
        );

        return response()->json(['data' => $review], 201);
    }
}
