<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use Tests\Feature\Storefront\StorefrontTestHelpers;

it('lists only public published events', function () {
    $published = StorefrontTestHelpers::publishedEvent();
    $draft = StorefrontTestHelpers::publishedEvent(['status' => EventStatus::Draft]);
    $private = StorefrontTestHelpers::publishedEvent(['visibility' => EventVisibility::Private]);

    $response = $this->getJson('/api/v1/public/events');

    $slugs = collect($response->json('data'))->pluck('slug')->all();
    expect($slugs)
        ->toContain($published->slug)
        ->not->toContain($draft->slug)
        ->not->toContain($private->slug);
});

it('returns 404 for unknown slug', function () {
    $this->getJson('/api/v1/public/events/does-not-exist')
        ->assertStatus(404);
});

it('returns tier availability on detail', function () {
    $event = StorefrontTestHelpers::publishedEvent();
    $tier = StorefrontTestHelpers::tier($event, ['online_quantity' => 7]);

    $response = $this->getJson('/api/v1/public/events/'.$event->slug);

    $response->assertOk();
    expect($response->json('data.tickets.0.available'))->toBe(7);
});

it('records a page view on detail', function () {
    $event = StorefrontTestHelpers::publishedEvent();
    StorefrontTestHelpers::tier($event);

    $this->getJson('/api/v1/public/events/'.$event->slug)->assertOk();

    $this->assertDatabaseHas('event_page_views', [
        'event_id' => $event->id,
        'event_type' => 'page_view',
    ]);
});

it('exposes the unlisted event by slug but hides it from index', function () {
    $unlisted = StorefrontTestHelpers::publishedEvent(['visibility' => EventVisibility::Unlisted]);
    StorefrontTestHelpers::tier($unlisted);

    $this->getJson('/api/v1/public/events/'.$unlisted->slug)->assertOk();

    $slugs = collect($this->getJson('/api/v1/public/events')->json('data'))->pluck('slug')->all();
    expect($slugs)->not->toContain($unlisted->slug);
});
