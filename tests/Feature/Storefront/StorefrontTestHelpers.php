<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketCategory;
use Illuminate\Support\Str;

class StorefrontTestHelpers
{
    public static function publishedEvent(array $overrides = []): Event
    {
        $org = Organization::factory()->create();

        $event = Event::query()->create(array_merge([
            'event_id' => (string) Str::uuid(),
            'organisation_id' => $org->uuid,
            'name' => 'Storefront Test '.Str::random(6),
            'slug' => 'storefront-test-'.strtolower(Str::random(8)),
            'status' => EventStatus::Published,
            'visibility' => EventVisibility::Public,
            'capacity' => 100,
            'starts_at' => now()->addDays(14),
            'ends_at' => now()->addDays(14)->addHours(4),
            'timezone' => 'UTC',
        ], $overrides));

        return $event->refresh();
    }

    public static function tier(Event $event, array $overrides = []): TicketCategory
    {
        return TicketCategory::query()->create(array_merge([
            'event_id' => $event->id,
            'organisation_id' => $event->organisation_id,
            'uuid' => (string) Str::uuid(),
            'name' => 'GA',
            'admission_type' => 'general_admission',
            'pass_type' => 'single_use',
            'base_price' => 50.00,
            'base_currency' => 'USD',
            'online_quantity' => 50,
            'sale_status' => 'on_sale',
            'is_visible' => true,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'sort_order' => 0,
        ], $overrides));
    }
}
