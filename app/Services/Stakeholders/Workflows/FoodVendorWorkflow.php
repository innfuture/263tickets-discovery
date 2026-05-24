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
 * Food / beverage vendor — money flows the OTHER direction
 * (vendor pays organizer for a booth slot). Default schedule is a
 * single booth-fee charge due 30 days out.
 */
class FoodVendorWorkflow implements StakeholderWorkflow
{
    public function type(): string
    {
        return StakeholderType::FoodVendor->value;
    }

    public function seedDeliverables(EventStakeholderEngagement $engagement): int
    {
        $count = 0;
        $eventStart = $engagement->event?->starts_at ?? Carbon::now()->addMonth();

        foreach (StakeholderType::FoodVendor->defaultDeliverables() as $row) {
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

        // Booth fee fully due in advance.
        return [
            ['description' => 'Booth fee', 'amount_cents' => $total, 'currency' => (string) $engagement->currency, 'due_offset_days' => -30],
        ];
    }

    public function requiredDocumentKinds(): array
    {
        return [
            StakeholderDocument::KIND_CONTRACT,
            StakeholderDocument::KIND_INSURANCE,
            StakeholderDocument::KIND_PERMIT,
        ];
    }
}
