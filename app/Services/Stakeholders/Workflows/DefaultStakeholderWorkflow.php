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
 * Catch-all fallback for stakeholder types without a dedicated
 * workflow: influencer, photographer, merchandiser. Uses the type's
 * default deliverables + a single end-of-event payment.
 */
class DefaultStakeholderWorkflow implements StakeholderWorkflow
{
    public function __construct(protected StakeholderType $type) {}

    public function type(): string
    {
        return $this->type->value;
    }

    public function seedDeliverables(EventStakeholderEngagement $engagement): int
    {
        $count = 0;
        $eventStart = $engagement->event?->starts_at ?? Carbon::now()->addMonth();

        foreach ($this->type->defaultDeliverables() as $row) {
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

        return [
            ['description' => 'Engagement fee (net-7)', 'amount_cents' => $total, 'currency' => (string) $engagement->currency, 'due_offset_days' => 7],
        ];
    }

    public function requiredDocumentKinds(): array
    {
        return [StakeholderDocument::KIND_CONTRACT];
    }
}
