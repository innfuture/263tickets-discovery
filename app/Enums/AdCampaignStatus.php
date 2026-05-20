<?php

namespace App\Enums;

enum AdCampaignStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Running',
            self::Paused => 'Paused',
            self::Ended => 'Ended',
            self::Failed => 'Failed',
        };
    }

    public function canActivate(): bool
    {
        return in_array($this, [self::Draft, self::Paused], true);
    }
}
