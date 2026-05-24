<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAddon;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddonManagementController extends Controller
{
    public function index(Organization $currentOrganization, Event $event): JsonResponse
    {
        $this->assertOwned($event, $currentOrganization);

        return response()->json([
            'data' => EventAddon::query()
                ->where('event_id', $event->id)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Organization $currentOrganization, Event $event, Request $request): JsonResponse
    {
        $this->assertOwned($event, $currentOrganization);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'min_per_order' => ['nullable', 'integer', 'min:0'],
            'max_per_order' => ['nullable', 'integer', 'min:1'],
            'requires_ticket' => ['nullable', 'boolean'],
            'is_visible' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'image_path' => ['nullable', 'string', 'max:500'],
        ]);

        $addon = EventAddon::create([
            'event_id' => $event->id,
            'organisation_id' => $currentOrganization->uuid,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price_cents' => $validated['price_cents'],
            'currency' => strtoupper($validated['currency']),
            'stock' => $validated['stock'] ?? null,
            'min_per_order' => (int) ($validated['min_per_order'] ?? 0),
            'max_per_order' => (int) ($validated['max_per_order'] ?? 10),
            'requires_ticket' => (bool) ($validated['requires_ticket'] ?? true),
            'is_visible' => (bool) ($validated['is_visible'] ?? true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'image_path' => $validated['image_path'] ?? null,
        ]);

        return response()->json(['data' => $addon], 201);
    }

    public function update(Organization $currentOrganization, EventAddon $addon, Request $request): JsonResponse
    {
        abort_if($addon->organisation_id !== $currentOrganization->uuid, 404);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'price_cents' => ['nullable', 'integer', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'min_per_order' => ['nullable', 'integer', 'min:0'],
            'max_per_order' => ['nullable', 'integer', 'min:1'],
            'requires_ticket' => ['nullable', 'boolean'],
            'is_visible' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $addon->fill(array_filter($validated, fn ($v) => $v !== null))->save();

        return response()->json(['data' => $addon->fresh()]);
    }

    public function destroy(Organization $currentOrganization, EventAddon $addon): JsonResponse
    {
        abort_if($addon->organisation_id !== $currentOrganization->uuid, 404);
        $addon->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    protected function assertOwned(Event $event, Organization $org): void
    {
        abort_if($event->organisation_id !== $org->uuid, 404);
    }
}
