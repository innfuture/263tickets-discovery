<?php

declare(strict_types=1);

namespace App\Services\Stakeholders;

use App\Models\AuditLog;
use App\Models\EngagementDispute;
use App\Models\EventStakeholderEngagement;
use App\Models\Stakeholder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Stakeholder ↔ organizer dispute workflow. Either side can open a
 * dispute on an active engagement; marketplace staff (or the
 * organizer-side reviewer) renders a verdict.
 *
 * Flow: open → (evidence accumulated) → under_review → resolved
 *       (resolution: upheld | partial | rejected) | withdrawn
 *
 * On open, the engagement's status flips to `disputed` so payment
 * processing pauses until resolution.
 */
class DisputeService
{
    public function open(
        EventStakeholderEngagement $engagement,
        string $raisedByType,
        int $raisedById,
        string $reasonCode,
        string $statement,
        array $evidence = [],
    ): EngagementDispute {
        if (! in_array($raisedByType, [
            EngagementDispute::RAISED_BY_ORGANIZER,
            EngagementDispute::RAISED_BY_STAKEHOLDER,
        ], true)) {
            throw new RuntimeException('Invalid raised_by_type.');
        }

        return DB::transaction(function () use ($engagement, $raisedByType, $raisedById, $reasonCode, $statement, $evidence) {
            $dispute = EngagementDispute::create([
                'event_stakeholder_engagement_id' => $engagement->id,
                'raised_by_type' => $raisedByType,
                'raised_by_id' => $raisedById,
                'reason_code' => $reasonCode,
                'initial_statement' => $statement,
                'evidence' => $evidence,
                'status' => EngagementDispute::STATUS_OPEN,
            ]);

            $engagement->forceFill([
                'status' => EventStakeholderEngagement::STATUS_DISPUTED,
            ])->save();

            AuditLog::create([
                'actor_type' => $raisedByType === EngagementDispute::RAISED_BY_STAKEHOLDER ? 'system' : 'user',
                'user_id' => $raisedByType === EngagementDispute::RAISED_BY_ORGANIZER ? $raisedById : null,
                'action' => 'dispute.opened',
                'resource_type' => EngagementDispute::class,
                'resource_id' => (string) $dispute->id,
                'after' => ['reason' => $reasonCode],
            ]);

            return $dispute;
        });
    }

    public function appendEvidence(EngagementDispute $dispute, array $evidence): EngagementDispute
    {
        $dispute->forceFill([
            'evidence' => array_merge((array) $dispute->evidence, $evidence),
            'status' => EngagementDispute::STATUS_UNDER_REVIEW,
        ])->save();

        return $dispute;
    }

    public function resolve(EngagementDispute $dispute, string $resolution, string $notes, User $reviewer): EngagementDispute
    {
        if (! in_array($resolution, [
            EngagementDispute::RESOLUTION_UPHELD,
            EngagementDispute::RESOLUTION_PARTIAL,
            EngagementDispute::RESOLUTION_REJECTED,
        ], true)) {
            throw new RuntimeException('Invalid resolution.');
        }

        return DB::transaction(function () use ($dispute, $resolution, $notes, $reviewer) {
            $dispute->forceFill([
                'status' => EngagementDispute::STATUS_RESOLVED,
                'resolution' => $resolution,
                'resolution_notes' => $notes,
                'resolved_by_user_id' => $reviewer->id,
                'resolved_at' => now(),
            ])->save();

            // Restore the engagement's status. If upheld, it's
            // typically cancelled; if rejected, back to active;
            // if partial, the reviewer's notes drive what happens
            // (e.g. payment reduction) — we leave as `active` so
            // the organizer can manually mark complete.
            $engagement = $dispute->engagement;
            if ($engagement) {
                $engagement->forceFill([
                    'status' => match ($resolution) {
                        EngagementDispute::RESOLUTION_UPHELD => EventStakeholderEngagement::STATUS_CANCELLED,
                        default => EventStakeholderEngagement::STATUS_ACTIVE,
                    },
                ])->save();
            }

            AuditLog::create([
                'user_id' => $reviewer->id,
                'actor_type' => 'user',
                'action' => 'dispute.resolved.'.$resolution,
                'resource_type' => EngagementDispute::class,
                'resource_id' => (string) $dispute->id,
                'after' => ['resolution' => $resolution],
            ]);

            return $dispute;
        });
    }

    public function withdraw(EngagementDispute $dispute, string $raisedByType, int $raisedById): EngagementDispute
    {
        if ($dispute->raised_by_type !== $raisedByType || $dispute->raised_by_id !== $raisedById) {
            throw new RuntimeException('Only the party that opened the dispute can withdraw it.');
        }
        $dispute->forceFill([
            'status' => EngagementDispute::STATUS_WITHDRAWN,
            'resolved_at' => now(),
        ])->save();

        $engagement = $dispute->engagement;
        if ($engagement?->status === EventStakeholderEngagement::STATUS_DISPUTED) {
            $engagement->forceFill(['status' => EventStakeholderEngagement::STATUS_ACTIVE])->save();
        }

        return $dispute;
    }
}
