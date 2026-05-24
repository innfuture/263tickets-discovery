<?php

declare(strict_types=1);

use App\Models\Order;
use Illuminate\Support\Str;
use Tests\Feature\Storefront\StorefrontTestHelpers;

it('accepts a refund request on a paid order with matching email', function () {
    $event = StorefrontTestHelpers::publishedEvent();
    $order = Order::query()->create([
        'uuid' => (string) Str::uuid(),
        'reference' => 'ORD-TEST-0001',
        'organisation_id' => $event->organisation_id,
        'event_id' => $event->id,
        'buyer_name' => 'Buyer', 'buyer_email' => 'buyer@example.com',
        'status' => 'paid', 'subtotal_cents' => 5000, 'total_cents' => 5000,
        'currency' => 'USD',
    ]);

    $this->postJson("/api/v1/public/orders/{$order->reference}/refund-request", [
        'contact_email' => 'buyer@example.com',
        'reason_code' => 'not_attending',
        'notes' => 'Plans changed.',
    ])->assertCreated()
        ->assertJsonPath('data.status', 'pending_review');

    $this->assertDatabaseHas('refund_requests', [
        'order_id' => $order->id,
        'reason_code' => 'not_attending',
    ]);
});

it('returns 404 when contact_email does not match buyer_email', function () {
    $event = StorefrontTestHelpers::publishedEvent();
    $order = Order::query()->create([
        'uuid' => (string) Str::uuid(),
        'reference' => 'ORD-TEST-0002',
        'organisation_id' => $event->organisation_id, 'event_id' => $event->id,
        'buyer_name' => 'Buyer', 'buyer_email' => 'buyer@example.com',
        'status' => 'paid', 'subtotal_cents' => 1000, 'total_cents' => 1000, 'currency' => 'USD',
    ]);

    $this->postJson("/api/v1/public/orders/{$order->reference}/refund-request", [
        'contact_email' => 'wrong@example.com',
        'reason_code' => 'not_attending',
    ])->assertStatus(404);
});

it('rejects refund on a non-refundable status', function () {
    $event = StorefrontTestHelpers::publishedEvent();
    $order = Order::query()->create([
        'uuid' => (string) Str::uuid(),
        'reference' => 'ORD-TEST-0003',
        'organisation_id' => $event->organisation_id, 'event_id' => $event->id,
        'buyer_name' => 'Buyer', 'buyer_email' => 'buyer@example.com',
        'status' => 'voided', 'subtotal_cents' => 1000, 'total_cents' => 1000, 'currency' => 'USD',
    ]);

    $this->postJson("/api/v1/public/orders/{$order->reference}/refund-request", [
        'contact_email' => 'buyer@example.com',
        'reason_code' => 'not_attending',
    ])->assertStatus(422)->assertJson(['error' => 'not_refundable']);
});

it('is idempotent per (order, email)', function () {
    $event = StorefrontTestHelpers::publishedEvent();
    $order = Order::query()->create([
        'uuid' => (string) Str::uuid(),
        'reference' => 'ORD-TEST-0004',
        'organisation_id' => $event->organisation_id, 'event_id' => $event->id,
        'buyer_name' => 'Buyer', 'buyer_email' => 'buyer@example.com',
        'status' => 'paid', 'subtotal_cents' => 1000, 'total_cents' => 1000, 'currency' => 'USD',
    ]);

    $a = $this->postJson("/api/v1/public/orders/{$order->reference}/refund-request", [
        'contact_email' => 'buyer@example.com', 'reason_code' => 'not_attending',
    ])->json('data.uuid');
    $b = $this->postJson("/api/v1/public/orders/{$order->reference}/refund-request", [
        'contact_email' => 'buyer@example.com', 'reason_code' => 'not_attending',
    ])->json('data.uuid');

    expect($a)->toBe($b);
});
