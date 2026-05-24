<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\EventBundle;
use App\Models\Organization;
use App\Services\Storefront\BundleManager;
use Illuminate\Http\JsonResponse;

/**
 *   GET /api/v1/public/organizers/{slug}/bundles  — list org's bundles
 *   GET /api/v1/public/bundles/{bundleSlug}       — bundle detail
 *
 * Buying a bundle goes through the existing checkout flow with
 * `bundle_slug` in the session metadata instead of items; OrderPaid
 * fans out to BundleManager via a listener to issue per-event tickets.
 */
class BundleController extends Controller
{
    public function __construct(protected BundleManager $bundles) {}

    public function listForOrganizer(string $slug): JsonResponse
    {
        $org = Organization::query()->where('slug', $slug)->first();
        if (! $org) {
            return response()->json(['error' => 'organization_not_found'], 404);
        }

        $bundles = EventBundle::query()
            ->where('organisation_id', $org->uuid)
            ->where('is_visible', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $bundles->filter(fn ($b) => $this->bundles->isAvailable($b))
                ->map(fn ($b) => $this->summary($b))
                ->values()
                ->all(),
        ]);
    }

    public function show(string $bundleSlug): JsonResponse
    {
        $bundle = EventBundle::query()
            ->with('includedEvents.event', 'includedEvents.category')
            ->where('slug', $bundleSlug)
            ->where('is_visible', true)
            ->first();

        if (! $bundle) {
            return response()->json(['error' => 'bundle_not_found'], 404);
        }

        $payload = $this->summary($bundle);
        $payload['included_events'] = $bundle->includedEvents->map(fn ($e) => [
            'event_slug' => $e->event?->slug,
            'event_name' => $e->event?->name,
            'starts_at' => optional($e->event?->starts_at)->toIso8601String(),
            'tier_name' => $e->category?->name,
            'quantity' => (int) $e->quantity,
        ])->all();

        return response()->json(['data' => $payload]);
    }

    /** @return array<string, mixed> */
    protected function summary(EventBundle $bundle): array
    {
        return [
            'uuid' => $bundle->uuid,
            'slug' => $bundle->slug,
            'name' => $bundle->name,
            'description' => $bundle->description,
            'price_cents' => (int) $bundle->price_cents,
            'currency' => $bundle->currency,
            'savings_cents' => $bundle->savings_cents !== null ? (int) $bundle->savings_cents : null,
            'remaining_capacity' => $bundle->remainingCapacity(),
            'is_available' => $this->bundles->isAvailable($bundle),
            'image_path' => $bundle->image_path,
        ];
    }
}
