<?php

namespace App\Enums;

enum TicketSaleStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Stopped = 'stopped';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'On sale',
            self::Paused => 'Paused',
            self::Stopped => 'Stopped',
        };
    }

    public function isAvailable(): bool
    {
        return $this === self::Active;
    }
}
