<?php

declare(strict_types=1);

use App\Models\Order;
use Illuminate\Support\Str;
use Tests\Feature\Storefront\StorefrontTestHelpers;

it('exports a buyer\'s orders when reference + email match', function () {
    $event = StorefrontTestHelpers::publishedEvent();
    $order = Order::query()->create([
        'uuid' => (string) Str::uuid(),
        'reference' => 'ORD-PRIV-001',
        'organisation_id' => $event->organisation_id, 'event_id' => $event->id,
        'buyer_name' => 'P', 'buyer_email' => 'p@example.com',
        'status' => 'paid', 'subtotal_cents' => 100, 'total_cents' => 100, 'currency' => 'USD',
    ]);

    $response = $this->postJson('/api/v1/public/privacy/export', [
        'email' => 'p@example.com',
        'order_reference' => $order->reference,
    ]);

    $response->assertOk();
    expect($response->json('data.orders'))->toHaveCount(1);
});

it('refuses export when proof fails', function () {
    $this->postJson('/api/v1/public/privacy/export', [
        'email' => 'p@example.com',
        'order_reference' => 'ORD-NOPE',
    ])->assertStatus(403);
});

it('anonymises rows on erase', function () {
    $event = StorefrontTestHelpers::publishedEvent();
    $order = Order::query()->create([
        'uuid' => (string) Str::uuid(),
        'reference' => 'ORD-PRIV-002',
        'organisation_id' => $event->organisation_id, 'event_id' => $event->id,
        'buyer_name' => 'P', 'buyer_email' => 'erase@example.com',
        'status' => 'paid', 'subtotal_cents' => 100, 'total_cents' => 100, 'currency' => 'USD',
    ]);

    $this->postJson('/api/v1/public/privacy/erase', [
        'email' => 'erase@example.com',
        'order_reference' => $order->reference,
    ])->assertOk();

    expect($order->refresh()->buyer_email)
        ->not->toBe('erase@example.com')
        ->and($order->buyer_email)->toContain('erased');
});
