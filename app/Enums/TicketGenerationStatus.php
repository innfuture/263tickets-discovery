<?php

namespace App\Enums;

enum TicketGenerationStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Generating…',
            self::Completed => 'Ready',
            self::Failed => 'Failed',
        };
    }
}
