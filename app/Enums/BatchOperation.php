<?php

namespace App\Enums;

/**
 * What an OfflineTicketBatch did to its category's inventory.
 *
 *   - Create    : initial batch generated at category creation. Always
 *                 exactly one of these per category, present in the
 *                 timeline as #1.
 *   - Increase  : additional tickets minted for an existing category.
 *                 The quantity column is the NUMBER ADDED.
 *   - Decrease  : tickets voided (not hard-deleted — preserves audit).
 *                 The quantity column is the NUMBER VOIDED.
 */
enum BatchOperation: string
{
    case Create = 'create';
    case Increase = 'increase';
    case Decrease = 'decrease';

    public function label(): string
    {
        return match ($this) {
            self::Create => 'Initial creation',
            self::Increase => 'Increase',
            self::Decrease => 'Decrease',
        };
    }

    /**
     * Sign of the inventory delta this op applies. Useful when computing
     * running balance from the batch ledger.
     */
    public function sign(): int
    {
        return match ($this) {
            self::Create, self::Increase => 1,
            self::Decrease => -1,
        };
    }
}
