<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Stakeholder categories. Each maps to a dedicated workflow class
 * (`StakeholderWorkflowRegistry::for($type)`) which encodes the
 * domain-specific rules: deliverable templates, payment terms,
 * default permissions, dashboard tabs.
 */
enum StakeholderType: string
{
    case Sponsor = 'sponsor';
    case MediaPartner = 'media_partner';
    case FoodVendor = 'food_vendor';
    case ServiceProvider = 'service_provider';
    case Influencer = 'influencer';
    case Photographer = 'photographer';
    case Merchandiser = 'merchandiser';

    public function label(): string
    {
        return match ($this) {
            self::Sponsor => 'Sponsor',
            self::MediaPartner => 'Media partner',
            self::FoodVendor => 'Food &amp; beverage vendor',
            self::ServiceProvider => 'Service provider',
            self::Influencer => 'Influencer / creator',
            self::Photographer => 'Photographer / videographer',
            self::Merchandiser => 'Merchandiser',
        };
    }

    /**
     * Default deliverable templates seeded into a fresh engagement
     * for this type. Organizers can edit / extend per-engagement.
     *
     * @return list<array{title: string, due_days_offset: int|null}>
     */
    public function defaultDeliverables(): array
    {
        return match ($this) {
            self::Sponsor => [
                ['title' => 'Submit brand assets (logo, copy)', 'due_days_offset' => -30],
                ['title' => 'Approve venue signage placement', 'due_days_offset' => -14],
                ['title' => 'Confirm hospitality guest list', 'due_days_offset' => -7],
            ],
            self::MediaPartner => [
                ['title' => 'Publish pre-event coverage', 'due_days_offset' => -14],
                ['title' => 'Deliver event-day coverage', 'due_days_offset' => 1],
                ['title' => 'Submit reach + engagement report', 'due_days_offset' => 14],
            ],
            self::FoodVendor => [
                ['title' => 'Submit menu + pricing', 'due_days_offset' => -21],
                ['title' => 'Provide health-permit documents', 'due_days_offset' => -14],
                ['title' => 'Confirm booth assignment', 'due_days_offset' => -7],
            ],
            self::ServiceProvider => [
                ['title' => 'Submit scope-of-work + crew list', 'due_days_offset' => -14],
                ['title' => 'Provide insurance certificate', 'due_days_offset' => -7],
                ['title' => 'Post-event debrief + invoice', 'due_days_offset' => 7],
            ],
            self::Influencer => [
                ['title' => 'Post pre-event content (story + post)', 'due_days_offset' => -7],
                ['title' => 'Live-cover event day', 'due_days_offset' => 0],
                ['title' => 'Submit post-event recap + metrics', 'due_days_offset' => 7],
            ],
            self::Photographer => [
                ['title' => 'Confirm shot list with organizer', 'due_days_offset' => -7],
                ['title' => 'Deliver edited gallery', 'due_days_offset' => 14],
            ],
            self::Merchandiser => [
                ['title' => 'Confirm SKU list + pricing', 'due_days_offset' => -14],
                ['title' => 'Submit booth requirements', 'due_days_offset' => -7],
            ],
        };
    }

    /** Required document kinds for engagements with this stakeholder type. */
    public function requiredDocuments(): array
    {
        return match ($this) {
            self::Sponsor => ['contract'],
            self::MediaPartner => ['contract'],
            self::FoodVendor => ['contract', 'insurance', 'permit'],
            self::ServiceProvider => ['contract', 'insurance'],
            self::Influencer => ['contract'],
            self::Photographer => ['contract'],
            self::Merchandiser => ['contract'],
        };
    }
}
