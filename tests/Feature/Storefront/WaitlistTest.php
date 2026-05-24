<?php

declare(strict_types=1);

use App\Models\WaitlistEntry;
use Tests\Feature\Storefront\StorefrontTestHelpers;

it('enrols an email on the waitlist', function () {
    $event = StorefrontTestHelpers::publishedEvent();

    $this->postJson("/api/v1/public/events/{$event->slug}/waitlist", [
        'email' => 'wait@example.com',
        'name' => 'Wait',
    ])->assertCreated();

    $this->assertDatabaseHas('waitlist_entries', [
        'event_id' => $event->id,
        'email' => 'wait@example.com',
    ]);
});

it('is idempotent for the same (event, tier, email)', function () {
    $event = StorefrontTestHelpers::publishedEvent();

    $this->postJson("/api/v1/public/events/{$event->slug}/waitlist", ['email' => 'wait@example.com']);
    $this->postJson("/api/v1/public/events/{$event->slug}/waitlist", ['email' => 'wait@example.com']);

    expect(WaitlistEntry::query()
        ->where('event_id', $event->id)
        ->where('email', 'wait@example.com')
        ->count())->toBe(1);
});
