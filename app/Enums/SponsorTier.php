<?php

namespace App\Enums;

/**
 * Sponsorship tiers in descending order of prominence. The tier drives
 * the size + grouping of sponsor logos on the public Event Details page.
 */
enum SponsorTier: string
{
    case Title = 'title';
    case Presenting = 'presenting';
    case Platinum = 'platinum';
    case Gold = 'gold';
    case Silver = 'silver';
    case Bronze = 'bronze';
    case Partner = 'partner';
    case Community = 'community';
    case Media = 'media';

    public function label(): string
    {
        return match ($this) {
            self::Title => 'Title sponsor',
            self::Presenting => 'Presenting sponsor',
            self::Platinum => 'Platinum',
            self::Gold => 'Gold',
            self::Silver => 'Silver',
            self::Bronze => 'Bronze',
            self::Partner => 'Partner',
            self::Community => 'Community',
            self::Media => 'Media partner',
        };
    }

    /**
     * Ranking weight used to order sponsors before secondary sorting by
     * sort_order. Lower number = more prominent.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Title => 1,
            self::Presenting => 2,
            self::Platinum => 3,
            self::Gold => 4,
            self::Silver => 5,
            self::Bronze => 6,
            self::Partner => 7,
            self::Community => 8,
            self::Media => 9,
        };
    }
}
