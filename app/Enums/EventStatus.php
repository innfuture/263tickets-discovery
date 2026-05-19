<?php

namespace App\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case SoldOut = 'sold_out';
    case Cancelled = 'cancelled';
    case Postponed = 'postponed';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::SoldOut => 'Sold Out',
            self::Cancelled => 'Cancelled',
            self::Postponed => 'Postponed',
            self::Ended => 'Ended',
        };
    }

    public function isPubliclyVisible(): bool
    {
        return match ($this) {
            self::Published, self::SoldOut, self::Postponed => true,
            self::Draft, self::Cancelled, self::Ended => false,
        };
    }

    public function isSellable(): bool
    {
        return $this === self::Published;
    }

    /**
     * @return list<EventStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Published, self::Cancelled],
            self::Published => [self::SoldOut, self::Cancelled, self::Postponed, self::Ended],
            self::SoldOut => [self::Published, self::Cancelled, self::Ended],
            self::Postponed => [self::Published, self::Cancelled, self::Ended],
            self::Cancelled, self::Ended => [],
        };
    }

    public function canTransitionTo(EventStatus $other): bool
    {
        return in_array($other, $this->allowedTransitions(), true);
    }
}
