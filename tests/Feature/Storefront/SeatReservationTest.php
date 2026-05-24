<?php

declare(strict_types=1);

use App\Models\CheckoutSession;
use App\Models\Seat;
use App\Models\SeatMap;
use Tests\Feature\Storefront\StorefrontTestHelpers;

it('returns the seat map for an event with one configured', function () {
    $event = StorefrontTestHelpers::publishedEvent();
    $tier = StorefrontTestHelpers::tier($event);
    $map = SeatMap::query()->create([
        'organisation_id' => $event->organisation_id,
        'event_id' => $event->id,
        'name' => 'Main Hall',
        'layout' => ['width' => 800, 'height' => 600],
    ]);
    Seat::query()->create([
        'seat_map_id' => $map->id, 'ticket_category_id' => $tier->id,
        'zone_code' => 'A', 'row_label' => '1', 'seat_label' => '1',
        'x' => 10, 'y' => 10, 'status' => Seat::STATUS_AVAILABLE,
    ]);

    $response = $this->getJson("/api/v1/public/events/{$event->slug}/seats");

    $response->assertOk();
    expect($response->json('data.seats'))->toHaveCount(1)
        ->and($response->json('data.seats.0.status'))->toBe('available');
});

it('holds seats and rejects a parallel claim', function () {
    $event = StorefrontTestHelpers::publishedEvent();
    $tier = StorefrontTestHelpers::tier($event);
    $map = SeatMap::query()->create([
        'organisation_id' => $event->organisation_id,
        'event_id' => $event->id, 'name' => 'Main Hall',
    ]);
    $seat = Seat::query()->create([
        'seat_map_id' => $map->id, 'ticket_category_id' => $tier->id,
        'zone_code' => 'A', 'row_label' => '1', 'seat_label' => '1',
        'status' => Seat::STATUS_AVAILABLE,
    ]);

    $first = $this->postJson('/api/v1/public/checkout/sessions', [
        'event_slug' => $event->slug, 'currency' => 'USD',
    ])->json('data.uuid');
    $second = $this->postJson('/api/v1/public/checkout/sessions', [
        'event_slug' => $event->slug, 'currency' => 'USD',
    ])->json('data.uuid');

    $this->patchJson("/api/v1/public/checkout/sessions/{$first}/seats", [
        'seat_uuids' => [$seat->uuid],
    ])->assertOk();

    $this->patchJson("/api/v1/public/checkout/sessions/{$second}/seats", [
        'seat_uuids' => [$seat->uuid],
    ])->assertStatus(409)->assertJson(['error' => 'seat_unavailable']);

    // First session's totals should reflect the seat's tier price.
    $firstSession = CheckoutSession::query()->where('uuid', $first)->first();
    expect((int) $firstSession->total_cents)->toBeGreaterThan(0);
});
