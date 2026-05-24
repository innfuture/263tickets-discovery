<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Models\CheckoutSession;
use App\Models\PaymentTransaction;
use Tests\Feature\Storefront\StorefrontTestHelpers;

it('creates a session, adds items, recomputes totals', function () {
    $event = StorefrontTestHelpers::publishedEvent();
    $tier = StorefrontTestHelpers::tier($event, ['base_price' => 25]);

    $create = $this->postJson('/api/v1/public/checkout/sessions', [
        'event_slug' => $event->slug,
        'currency' => 'USD',
    ]);
    $create->assertCreated();
    $uuid = $create->json('data.uuid');

    $items = $this->patchJson("/api/v1/public/checkout/sessions/{$uuid}/items", [
        'items' => [['ticket_category_id' => $tier->id, 'quantity' => 2]],
    ])->assertOk();

    expect($items->json('data.totals.subtotal_cents'))->toBe(5000);
});

it('rejects an oversell', function () {
    $event = StorefrontTestHelpers::publishedEvent();
    $tier = StorefrontTestHelpers::tier($event, ['online_quantity' => 3]);

    $uuid = $this->postJson('/api/v1/public/checkout/sessions', [
        'event_slug' => $event->slug, 'currency' => 'USD',
    ])->json('data.uuid');

    $this->patchJson("/api/v1/public/checkout/sessions/{$uuid}/items", [
        'items' => [['ticket_category_id' => $tier->id, 'quantity' => 5]],
    ])->assertStatus(422)->assertJson(['error' => 'inventory_unavailable']);
});

it('idempotency_key returns the same session', function () {
    $event = StorefrontTestHelpers::publishedEvent();
    StorefrontTestHelpers::tier($event);

    $a = $this->postJson('/api/v1/public/checkout/sessions', [
        'event_slug' => $event->slug, 'currency' => 'USD', 'idempotency_key' => 'abc-123',
    ])->json('data.uuid');

    $b = $this->postJson('/api/v1/public/checkout/sessions', [
        'event_slug' => $event->slug, 'currency' => 'USD', 'idempotency_key' => 'abc-123',
    ])->json('data.uuid');

    expect($a)->toBe($b);
});

it('rejects currency the tier is not priced in', function () {
    $event = StorefrontTestHelpers::publishedEvent();
    $tier = StorefrontTestHelpers::tier($event, ['base_currency' => 'USD']);

    $uuid = $this->postJson('/api/v1/public/checkout/sessions', [
        'event_slug' => $event->slug, 'currency' => 'EUR',
    ])->json('data.uuid');

    $this->patchJson("/api/v1/public/checkout/sessions/{$uuid}/items", [
        'items' => [['ticket_category_id' => $tier->id, 'quantity' => 1]],
    ])->assertStatus(422)->assertJson(['error' => 'currency_mismatch']);
});

it('payment success triggers fulfilment via observer', function () {
    $event = StorefrontTestHelpers::publishedEvent();
    $tier = StorefrontTestHelpers::tier($event);

    $uuid = $this->postJson('/api/v1/public/checkout/sessions', [
        'event_slug' => $event->slug, 'currency' => 'USD',
    ])->json('data.uuid');

    $this->patchJson("/api/v1/public/checkout/sessions/{$uuid}/items", [
        'items' => [['ticket_category_id' => $tier->id, 'quantity' => 1]],
    ])->assertOk();
    $this->patchJson("/api/v1/public/checkout/sessions/{$uuid}/attendees", [
        'buyer_name' => 'Test', 'buyer_email' => 'test@example.com',
    ])->assertOk();

    $session = CheckoutSession::query()->where('uuid', $uuid)->first();
    $tx = PaymentTransaction::query()->create([
        'organization_id' => 1,
        'gateway' => 'sandbox',
        'reference' => 'CHK-'.$uuid,
        'amount_minor' => $session->total_cents,
        'currency' => 'USD',
        'status' => 'pending',
        'metadata' => ['checkout_session_uuid' => $uuid],
    ]);
    $session->forceFill(['payment_transaction_id' => $tx->id, 'status' => 'paying'])->save();

    // Simulate gateway settling.
    $tx->markStatus(PaymentStatus::CAPTURED, ['settled' => true]);

    $session->refresh();
    expect($session->status->value)->toBe('completed')
        ->and($session->order_id)->not->toBeNull();
});

it('is idempotent when the gateway double-fires settle', function () {
    $event = StorefrontTestHelpers::publishedEvent();
    $tier = StorefrontTestHelpers::tier($event);

    $uuid = $this->postJson('/api/v1/public/checkout/sessions', [
        'event_slug' => $event->slug, 'currency' => 'USD',
    ])->json('data.uuid');
    $this->patchJson("/api/v1/public/checkout/sessions/{$uuid}/items", [
        'items' => [['ticket_category_id' => $tier->id, 'quantity' => 1]],
    ]);
    $this->patchJson("/api/v1/public/checkout/sessions/{$uuid}/attendees", [
        'buyer_name' => 'Test', 'buyer_email' => 'test@example.com',
    ]);

    $session = CheckoutSession::query()->where('uuid', $uuid)->first();
    $tx = PaymentTransaction::query()->create([
        'organization_id' => 1, 'gateway' => 'sandbox',
        'reference' => 'CHK-'.$uuid, 'amount_minor' => $session->total_cents,
        'currency' => 'USD', 'status' => 'pending',
        'metadata' => ['checkout_session_uuid' => $uuid],
    ]);
    $session->forceFill(['payment_transaction_id' => $tx->id, 'status' => 'paying'])->save();

    $tx->markStatus(PaymentStatus::CAPTURED);
    $firstOrderId = $session->refresh()->order_id;

    // Second fire — shouldn't create a second order.
    $tx->markStatus(PaymentStatus::CAPTURED);
    expect($session->refresh()->order_id)->toBe($firstOrderId);
});

it('payment failure releases the holds and frees inventory', function () {
    $event = StorefrontTestHelpers::publishedEvent();
    $tier = StorefrontTestHelpers::tier($event, ['online_quantity' => 5]);

    $uuid = $this->postJson('/api/v1/public/checkout/sessions', [
        'event_slug' => $event->slug, 'currency' => 'USD',
    ])->json('data.uuid');
    $this->patchJson("/api/v1/public/checkout/sessions/{$uuid}/items", [
        'items' => [['ticket_category_id' => $tier->id, 'quantity' => 3]],
    ]);

    $session = CheckoutSession::query()->where('uuid', $uuid)->first();
    $session->forceFill(['status' => 'paying'])->save();
    $tx = PaymentTransaction::query()->create([
        'organization_id' => 1, 'gateway' => 'sandbox',
        'reference' => 'CHK-'.$uuid, 'amount_minor' => 1000, 'currency' => 'USD',
        'status' => 'pending', 'metadata' => ['checkout_session_uuid' => $uuid],
    ]);
    $session->forceFill(['payment_transaction_id' => $tx->id])->save();

    $tx->markStatus(PaymentStatus::FAILED);

    expect($session->refresh()->status->value)->toBe('cancelled')
        ->and($session->items()->count())->toBe(0);
});
