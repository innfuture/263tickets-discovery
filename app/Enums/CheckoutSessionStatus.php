<?php

declare(strict_types=1);

namespace App\Enums;

enum CheckoutSessionStatus: string
{
    case Open = 'open';
    case Paying = 'paying';
    case Completed = 'completed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function isMutable(): bool
    {
        return $this === self::Open;
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Expired, self::Cancelled => true,
            default => false,
        };
    }

    public function holdsInventory(): bool
    {
        return match ($this) {
            self::Open, self::Paying => true,
            default => false,
        };
    }
}
