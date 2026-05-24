<?php

declare(strict_types=1);

namespace App\Services\Stakeholders;

use App\Models\Event;
use App\Models\EventStakeholderEngagement;
use App\Models\Stakeholder;
use App\Models\StakeholderApplication;
use App\Models\StakeholderInvitation;
use App\Models\StakeholderPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Single entry-point for transitioning Stakeholder ↔ Event into a
 * contracted engagement. Three intake paths converge here:
 *
 *   1. Invitation accepted by stakeholder.
 *   2. Application approved by organizer.
 *   3. Direct creation by organizer (back-office shortcut).
 *
 * On creation, the workflow registry seeds the default deliverables
 * and payment schedule for the engagement type.
 */
class StakeholderEngagementService
{
    public function __construct(protected StakeholderWorkflowRegistry $workflows) {}

    public function fromInvitation(StakeholderInvitation $invitation, array $terms): EventStakeholderEngagement
    {
        if ($invitation->status !== StakeholderInvitation::STATUS_PENDING) {
            throw new RuntimeException('Invitation is no longer pending.');
        }

        return DB::transaction(function () use ($invitation, $terms) {
            $engagement = $this->create(
                event: $invitation->event,
                stakeholder: $invitation->stakeholder,
                engagementType: (string) $invitation->engagement_type,
                terms: array_merge((array) $invitation->proposed_terms, $terms),
                invitation: $invitation,
            );

            $invitation->forceFill([
                'status' => StakeholderInvitation::STATUS_ACCEPTED,
                'responded_at' => Carbon::now(),
            ])->save();

            return $engagement;
        });
    }

    public function fromApplication(StakeholderApplication $application, array $terms, int $approverUserId): EventStakeholderEngagement
    {
        if ($application->status === StakeholderApplication::STATUS_APPROVED) {
            throw new RuntimeException('Application already approved.');
        }

        return DB::transaction(function () use ($application, $terms, $approverUserId) {
            $engagement = $this->create(
                event: $application->event,
                stakeholder: $application->stakeholder,
                engagementType: (string) $application->engagement_type,
                terms: array_merge((array) $application->proposed_terms, $terms),
                application: $application,
            );

            $application->forceFill([
                'status' => StakeholderApplication::STATUS_APPROVED,
                'responded_at' => Carbon::now(),
                'reviewed_by_user_id' => $approverUserId,
            ])->save();

            return $engagement;
        });
    }

    /** @internal — also used directly by back-office shortcut. */
    public function create(
        Event $event,
        Stakeholder $stakeholder,
        string $engagementType,
        array $terms,
        ?StakeholderInvitation $invitation = null,
        ?StakeholderApplication $application = null,
    ): EventStakeholderEngagement {
        $existing = EventStakeholderEngagement::query()
            ->where('event_id', $event->id)
            ->where('stakeholder_id', $stakeholder->id)
            ->where('engagement_type', $engagementType)
            ->first();
        if ($existing) {
            return $existing;
        }

        $engagement = EventStakeholderEngagement::create([
            'event_id' => $event->id,
            'stakeholder_id' => $stakeholder->id,
            'stakeholder_invitation_id' => $invitation?->id,
            'stakeholder_application_id' => $application?->id,
            'engagement_type' => $engagementType,
            'tier' => $terms['tier'] ?? null,
            'terms' => $terms,
            'agreed_amount_cents' => isset($terms['amount_cents']) ? (int) $terms['amount_cents'] : null,
            'currency' => $terms['currency'] ?? null,
            'status' => EventStakeholderEngagement::STATUS_ACTIVE,
            'starts_at' => $event->starts_at,
            'ends_at' => $event->ends_at,
        ]);

        $workflow = $this->workflows->for($engagementType);
        $workflow->seedDeliverables($engagement);
        $this->seedPaymentSchedule($engagement, $workflow);

        return $engagement;
    }

    public function complete(EventStakeholderEngagement $engagement): EventStakeholderEngagement
    {
        $engagement->forceFill([
            'status' => EventStakeholderEngagement::STATUS_COMPLETED,
            'completed_at' => Carbon::now(),
        ])->save();

        return $engagement;
    }

    public function cancel(EventStakeholderEngagement $engagement, ?string $reason = null): EventStakeholderEngagement
    {
        $engagement->forceFill([
            'status' => EventStakeholderEngagement::STATUS_CANCELLED,
            'cancelled_at' => Carbon::now(),
            'terms' => array_merge((array) $engagement->terms, ['cancel_reason' => $reason]),
        ])->save();

        return $engagement;
    }

    protected function seedPaymentSchedule(EventStakeholderEngagement $engagement, $workflow): void
    {
        $schedule = $workflow->defaultPaymentSchedule($engagement);
        $eventStart = $engagement->event?->starts_at ?? Carbon::now()->addMonth();

        foreach ($schedule as $row) {
            StakeholderPayment::create([
                'event_stakeholder_engagement_id' => $engagement->id,
                'description' => $row['description'],
                'amount_cents' => (int) $row['amount_cents'],
                'currency' => (string) $row['currency'],
                'due_at' => $eventStart->copy()->addDays((int) ($row['due_offset_days'] ?? 0)),
                'status' => StakeholderPayment::STATUS_SCHEDULED,
            ]);
        }
    }
}
