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
 * Generic service provider — security, AV, cleaning, equipment
 * rental. Default schedule is 30% deposit + 70% net-30 after event.
 */
class ServiceProviderWorkflow implements StakeholderWorkflow
{
    public function type(): string
    {
        return StakeholderType::ServiceProvider->value;
    }

    public function seedDeliverables(EventStakeholderEngagement $engagement): int
    {
        $count = 0;
        $eventStart = $engagement->event?->starts_at ?? Carbon::now()->addMonth();

        foreach (StakeholderType::ServiceProvider->defaultDeliverables() as $row) {
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

        $deposit = (int) floor($total * 0.30);
        $balance = $total - $deposit;

        return [
            ['description' => 'Deposit (30%)', 'amount_cents' => $deposit, 'currency' => (string) $engagement->currency, 'due_offset_days' => -14],
            ['description' => 'Balance (net-30 after event)', 'amount_cents' => $balance, 'currency' => (string) $engagement->currency, 'due_offset_days' => 30],
        ];
    }

    public function requiredDocumentKinds(): array
    {
        return [
            StakeholderDocument::KIND_CONTRACT,
            StakeholderDocument::KIND_INSURANCE,
        ];
    }
}
