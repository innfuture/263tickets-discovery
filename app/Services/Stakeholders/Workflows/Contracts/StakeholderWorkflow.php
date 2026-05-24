<?php

declare(strict_types=1);

namespace App\Services\Stakeholders\Workflows\Contracts;

use App\Models\EventStakeholderEngagement;

/**
 * Strategy interface — per-type rules for an engagement's lifecycle.
 * Each StakeholderType has one impl; `StakeholderWorkflowRegistry`
 * is the lookup.
 *
 * Bind a custom impl in a downstream provider to override the
 * default behaviour without editing core.
 */
interface StakeholderWorkflow
{
    /** Type identifier — matches `StakeholderType::value`. */
    public function type(): string;

    /**
     * Seed the standard deliverables when an engagement is created.
     * Returns the count of rows inserted.
     */
    public function seedDeliverables(EventStakeholderEngagement $engagement): int;

    /**
     * Build the default payment schedule for an engagement (e.g.
     * 50% upfront / 50% after delivery for sponsors, milestone-
     * based for service providers).
     *
     * @return list<array{description: string, amount_cents: int, currency: string, due_offset_days: int}>
     */
    public function defaultPaymentSchedule(EventStakeholderEngagement $engagement): array;

    /** Document kinds the engagement REQUIRES before going active. */
    public function requiredDocumentKinds(): array;
}
