<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Storefront;

use App\Http\Controllers\Controller;
use App\Models\BundleEvent;
use App\Models\EventBundle;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Organizer-side CRUD for EventBundles. Mounted under the existing
 * `{current_organization}` web group; the `current_organization`
 * binding is the active Organization model.
 */
class BundleManagementController extends Controller
{
    public function index(Organization $currentOrganization): JsonResponse
    {
        return response()->json([
            'data' => EventBundle::query()
                ->where('organisation_id', $currentOrganization->uuid)
                ->with('includedEvents.event:id,name,slug,starts_at')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function store(Organization $currentOrganization, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'savings_cents' => ['nullable', 'integer', 'min:0'],
            'total_capacity' => ['nullable', 'integer', 'min:1'],
            'sales_start_at' => ['nullable', 'date'],
            'sales_end_at' => ['nullable', 'date'],
            'is_visible' => ['nullable', 'boolean'],
            'included_events' => ['required', 'array', 'min:1'],
            'included_events.*.event_id' => ['required', 'integer'],
            'included_events.*.ticket_category_id' => ['nullable', 'integer'],
            'included_events.*.quantity' => ['nullable', 'integer', 'min:1'],
            'included_events.*.sort_order' => ['nullable', 'integer'],
        ]);

        $bundle = EventBundle::create([
            'organisation_id' => $currentOrganization->uuid,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price_cents' => $validated['price_cents'],
            'currency' => strtoupper($validated['currency']),
            'savings_cents' => $validated['savings_cents'] ?? null,
            'total_capacity' => $validated['total_capacity'] ?? null,
            'sales_start_at' => $validated['sales_start_at'] ?? null,
            'sales_end_at' => $validated['sales_end_at'] ?? null,
            'is_visible' => (bool) ($validated['is_visible'] ?? true),
        ]);

        foreach ($validated['included_events'] as $row) {
            BundleEvent::create([
                'event_bundle_id' => $bundle->id,
                'event_id' => (int) $row['event_id'],
                'ticket_category_id' => $row['ticket_category_id'] ?? null,
                'quantity' => (int) ($row['quantity'] ?? 1),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ]);
        }

        return response()->json(['data' => $bundle->load('includedEvents')], 201);
    }

    public function update(Organization $currentOrganization, EventBundle $bundle, Request $request): JsonResponse
    {
        abort_if($bundle->organisation_id !== $currentOrganization->uuid, 404);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'price_cents' => ['nullable', 'integer', 'min:0'],
            'savings_cents' => ['nullable', 'integer', 'min:0'],
            'total_capacity' => ['nullable', 'integer', 'min:0'],
            'sales_start_at' => ['nullable', 'date'],
            'sales_end_at' => ['nullable', 'date'],
            'is_visible' => ['nullable', 'boolean'],
        ]);

        $bundle->fill(array_filter($validated, fn ($v) => $v !== null))->save();

        return response()->json(['data' => $bundle->fresh('includedEvents')]);
    }

    public function destroy(Organization $currentOrganization, EventBundle $bundle): JsonResponse
    {
        abort_if($bundle->organisation_id !== $currentOrganization->uuid, 404);
        $bundle->delete();

        return response()->json(['data' => ['ok' => true]]);
    }
}
