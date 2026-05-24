<?php

declare(strict_types=1);

namespace App\Services\Stakeholders\Workflows;

use App\Enums\StakeholderType;
use App\Models\EventStakeholderEngagement;
use App\Models\StakeholderDeliverable;
use App\Models\StakeholderDocument;
use App\Services\Stakeholders\Workflows\Contracts\StakeholderWorkflow;
use Illuminate\Support\Carbon;

/**
 * Sponsorship engagements. Money flows organizer ← sponsor, so the
 * default payment schedule splits 50% on signing / 50% on event-start
 * (industry-standard for tiered sponsorships).
 */
class SponsorWorkflow implements StakeholderWorkflow
{
    public function type(): string
    {
        return StakeholderType::Sponsor->value;
    }

    public function seedDeliverables(EventStakeholderEngagement $engagement): int
    {
        $count = 0;
        $eventStart = $engagement->event?->starts_at ?? Carbon::now()->addMonth();

        foreach (StakeholderType::Sponsor->defaultDeliverables() as $row) {
            StakeholderDeliverable::create([
                'event_stakeholder_engagement_id' => $engagement->id,
                'title' => $row['title'],
                'due_at' => $row['due_days_offset'] !== null
                    ? $eventStart->copy()->addDays($row['due_days_offset'])
                    : null,
                'status' => StakeholderDeliverable::STATUS_PENDING,
            ]);
            $count++;
        }

        return $count;
    }

    public function defaultPaymentSchedule(EventStakeholderEngagement $engagement): array
    {
        $total = (int) ($engagement->agreed_amount_cents ?? 0);
        if ($total <= 0) {
            return [];
        }

        $half = (int) floor($total / 2);
        $remainder = $total - $half;

        return [
            ['description' => 'Sponsorship deposit (50%)', 'amount_cents' => $half, 'currency' => (string) $engagement->currency, 'due_offset_days' => -45],
            ['description' => 'Sponsorship balance (50%)', 'amount_cents' => $remainder, 'currency' => (string) $engagement->currency, 'due_offset_days' => 0],
        ];
    }

    public function requiredDocumentKinds(): array
    {
        return [StakeholderDocument::KIND_CONTRACT];
    }
}
