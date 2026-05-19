<?php

namespace Database\Seeders;

use App\Models\EventCategory;
use Illuminate\Database\Seeder;

class EventCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Music', 'slug' => 'music', 'description' => 'Concerts, festivals, and live performances.'],
            ['name' => 'Business & Professional', 'slug' => 'business-professional', 'description' => 'Conferences, networking events, and trade shows.'],
            ['name' => 'Food & Drink', 'slug' => 'food-drink', 'description' => 'Tastings, classes, and culinary experiences.'],
            ['name' => 'Community & Culture', 'slug' => 'community-culture', 'description' => 'Local meetups, festivals, and cultural events.'],
            ['name' => 'Performing & Visual Arts', 'slug' => 'arts', 'description' => 'Theatre, exhibitions, comedy, and dance.'],
            ['name' => 'Film, Media & Entertainment', 'slug' => 'film-media-entertainment', 'description' => 'Screenings, premieres, and media events.'],
            ['name' => 'Sports & Fitness', 'slug' => 'sports-fitness', 'description' => 'Tournaments, races, and fitness classes.'],
            ['name' => 'Health & Wellness', 'slug' => 'health-wellness', 'description' => 'Wellness retreats, yoga, and medical events.'],
            ['name' => 'Science & Technology', 'slug' => 'science-technology', 'description' => 'Hackathons, tech talks, and science fairs.'],
            ['name' => 'Travel & Outdoor', 'slug' => 'travel-outdoor', 'description' => 'Adventures, tours, and outdoor experiences.'],
            ['name' => 'Charity & Causes', 'slug' => 'charity-causes', 'description' => 'Fundraisers, galas, and volunteer drives.'],
            ['name' => 'Religion & Spirituality', 'slug' => 'religion-spirituality', 'description' => 'Worship gatherings and spiritual retreats.'],
            ['name' => 'Family & Education', 'slug' => 'family-education', 'description' => 'Workshops, classes, and family-friendly events.'],
            ['name' => 'Seasonal & Holiday', 'slug' => 'seasonal-holiday', 'description' => 'Holidays, parties, and seasonal celebrations.'],
            ['name' => 'Government & Politics', 'slug' => 'government-politics', 'description' => 'Town halls, debates, and civic events.'],
            ['name' => 'Fashion & Beauty', 'slug' => 'fashion-beauty', 'description' => 'Fashion shows, beauty events, and pop-ups.'],
            ['name' => 'Home & Lifestyle', 'slug' => 'home-lifestyle', 'description' => 'DIY workshops, home expos, and design events.'],
            ['name' => 'Auto, Boat & Air', 'slug' => 'auto-boat-air', 'description' => 'Auto shows, boat expos, and air shows.'],
            ['name' => 'Hobbies & Special Interest', 'slug' => 'hobbies', 'description' => 'Hobby groups, conventions, and special interest events.'],
            ['name' => 'Other', 'slug' => 'other', 'description' => "Events that don't fit other categories."],
        ];

        foreach ($categories as $index => $cat) {
            EventCategory::updateOrCreate(
                ['slug' => $cat['slug']],
                [
                    'name' => $cat['name'],
                    'description' => $cat['description'],
                    'is_active' => true,
                    'sort_order' => ($index + 1) * 10,
                ],
            );
        }

        $this->command?->info(sprintf(
            'Seeded %d event categories.',
            count($categories),
        ));
    }
}
