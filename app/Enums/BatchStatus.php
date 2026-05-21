<?php

namespace App\Enums;

/**
 * Lifecycle of one OfflineTicketBatch row.
 *
 *   - Pending    : queued, action has not started yet
 *   - Processing : action is iterating ticket batches (50-at-a-time inserts
 *                  for create/increase; LIFO row voids for decrease)
 *   - Completed  : all requested tickets either minted or voided; the
 *                  batch's actual_quantity equals the request unless
 *                  a decrease was clamped by available inventory
 *   - Failed     : action threw; partial work was rolled back by the
 *                  enclosing DB transaction. Operator can retry by
 *                  starting a fresh batch
 */
enum BatchStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Failed => true,
            default => false,
        };
    }
}
