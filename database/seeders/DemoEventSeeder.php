<?php

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoEventSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->first();

        if (! $user) {
            throw new RuntimeException(
                'No user found. Run DatabaseSeeder or sign in once before seeding the demo event.',
            );
        }

        $team = $user->currentTeam
            ?? $user->personalTeam()
            ?? $user->teams()->first();

        if (! $team) {
            throw new RuntimeException(
                "User {$user->email} has no team. Create or join a team first.",
            );
        }

        $category = EventCategory::firstOrCreate(
            ['slug' => 'music'],
            [
                'name' => 'Music',
                'description' => 'Concerts, festivals, and live performances.',
                'is_active' => true,
                'sort_order' => 10,
            ],
        );

        $event = Event::updateOrCreate(
            ['slug' => 'harare-spring-music-festival-2026'],
            [
                'organisation_id' => $team->uuid,
                'created_by_user_id' => $user->id,
                'category_id' => $category->id,

                'name' => 'Harare Spring Music Festival 2026',
                'short_description' => 'An unforgettable two-day celebration of African music featuring 20+ artists across three stages.',
                'description' => <<<'MD'
                    Two days. Three stages. Twenty-plus artists. The Harare Spring Music Festival returns for its fourth year — bigger, louder, and more diverse than ever.

                    Headlining this year's main stage are Jah Prayzah, Sho Madjozi, and Tems, with supporting acts spanning Afrobeats, Amapiano, Sungura, and contemporary Zim Hip-Hop. Side stages host emerging artists from across Southern Africa, an acoustic tent for intimate sets, and a late-night DJ stage running until 2 AM both nights.

                    Food vendors from 30+ local restaurants, craft markets, and a dedicated family zone for early afternoons. Free shuttle service from CBD pickup points starting 16:00 each day.

                    This is a strictly 16+ event. Valid government-issued ID required for entry. Re-entry is not permitted once you have left the venue.
                    MD,

                'status' => EventStatus::Published->value,
                'visibility' => EventVisibility::Public->value,
                'is_featured' => true,
                'published_at' => '2026-08-01 09:00:00',

                'starts_at' => '2026-11-14 17:00:00+02:00',
                'ends_at' => '2026-11-15 23:00:00+02:00',
                'timezone' => 'Africa/Harare',
                'sales_start_at' => '2026-08-01 09:00:00+02:00',
                'sales_end_at' => '2026-11-14 16:00:00+02:00',

                'venue_name' => 'Harare International Conference Centre',
                'address_line_1' => '1 Pennefather Avenue',
                'address_line_2' => 'Belvedere',
                'city' => 'Harare',
                'region' => 'Harare Province',
                'country_code' => 'ZW',
                'postal_code' => 'H100',
                'latitude' => -17.8252000,
                'longitude' => 31.0335000,
                'is_online' => false,
                'online_url' => null,

                'banner_image_path' => 'events/banners/harare-spring-fest-2026.jpg',
                'og_image_path' => 'events/og/harare-spring-fest-2026.jpg',

                'meta_title' => 'Harare Spring Music Festival 2026 | Tickets',
                'meta_description' => 'Two days of live African music in Harare — 20+ artists, three stages, food, and craft markets. Tickets on sale 1 August.',
                'tags' => ['music', 'festival', 'live', 'outdoor', 'weekend', 'afrobeats', 'amapiano'],

                'capacity' => 5000,
                'minimum_age' => 16,
                'refund_policy' => 'Full refunds up to 30 days before the event. 50% refund between 30 and 14 days before. No refunds within 14 days of the event date, except in the case of event cancellation or postponement, where full refunds will be processed automatically to the original payment method within 14 business days.',
                'terms' => "By purchasing a ticket you agree to: (1) abide by venue rules and security searches at entry; (2) accept that the event may be photographed and recorded; (3) no outside food, drinks, or recording equipment; (4) no refunds on lost or damaged tickets; (5) the organiser reserves the right to refuse entry for any reason.",

                'contact_email' => 'tickets@hararespringfest.zw',
                'contact_phone' => '+263 242 700 000',
            ],
        );

        // tickets_sold_count is intentionally not fillable — set via the
        // reconcile helper so the demo card shows realistic progress.
        $event->reconcileTicketsSold(1247);

        $this->command?->info(sprintf(
            'Demo event seeded: %s (team: %s, slug: %s)',
            $event->name,
            $team->slug,
            $event->slug,
        ));
    }
}
