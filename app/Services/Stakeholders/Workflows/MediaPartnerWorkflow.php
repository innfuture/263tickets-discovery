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
 * Media partner — most engagements are exchange-of-value (coverage
 * for tickets / access), so the default payment schedule is empty.
 * Override by setting `agreed_amount_cents` if there's an actual fee.
 */
class MediaPartnerWorkflow implements StakeholderWorkflow
{
    public function type(): string
    {
        return StakeholderType::MediaPartner->value;
    }

    public function seedDeliverables(EventStakeholderEngagement $engagement): int
    {
        $count = 0;
        $eventStart = $engagement->event?->starts_at ?? Carbon::now()->addMonth();

        foreach (StakeholderType::MediaPartner->defaultDeliverables() as $row) {
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

        // Single post-delivery payment.
        return [
            ['description' => 'Media partner fee (paid after final report)', 'amount_cents' => $total, 'currency' => (string) $engagement->currency, 'due_offset_days' => 30],
        ];
    }

    public function requiredDocumentKinds(): array
    {
        return [StakeholderDocument::KIND_CONTRACT];
    }
}
