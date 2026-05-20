<?php

namespace App\Enums;

enum PassType: string
{
    /** Ticket can be scanned exactly once (entry only). */
    case Single = 'single';

    /** Ticket can be scanned on each re-entry up to a defined count. */
    case Multiple = 'multiple';

    /** Ticket can be scanned unlimited times. */
    case Unlimited = 'unlimited';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single Entry',
            self::Multiple => 'Multiple Re-entry',
            self::Unlimited => 'Unlimited Entry',
        };
    }
}
