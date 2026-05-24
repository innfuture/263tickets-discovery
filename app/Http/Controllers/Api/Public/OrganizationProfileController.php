<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Storefront\PublicEventQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public organizer profile + their event list. No auth.
 *
 * GET /api/v1/public/organizers/{slug}
 * GET /api/v1/public/organizers/{slug}/events
 */
class OrganizationProfileController extends Controller
{
    public function __construct(protected PublicEventQuery $query) {}

    public function show(string $slug): JsonResponse
    {
        $org = Organization::query()->where('slug', $slug)->first();
        if (! $org) {
            return response()->json(['error' => 'organization_not_found'], 404);
        }

        return response()->json([
            'data' => [
                'uuid' => $org->uuid,
                'slug' => $org->slug,
                'name' => $org->brand_name ?: $org->name,
                'tagline' => $org->tagline,
                'description' => $org->description,
                'logo_url' => $org->logoUrl(),
                'banner_url' => $org->bannerUrl(),
                'social_links' => $org->socialLinks(),
                'is_verified' => (bool) $org->is_verified,
                'city' => $org->city,
                'country_code' => $org->country_code,
            ],
        ]);
    }

    public function events(string $slug, Request $request): JsonResponse
    {
        $org = Organization::query()->where('slug', $slug)->first();
        if (! $org) {
            return response()->json(['error' => 'organization_not_found'], 404);
        }

        $perPage = min(
            (int) $request->integer('per_page', (int) config('storefront.discovery.default_page_size', 20)),
            (int) config('storefront.discovery.max_page_size', 100),
        );
        $page = $this->query->byOrganizer($org, $perPage);

        return response()->json([
            'data' => $page->getCollection()->map(fn ($e) => [
                'slug' => $e->slug,
                'name' => $e->name,
                'starts_at' => optional($e->starts_at)->toIso8601String(),
                'is_sold_out' => $e->isSoldOut(),
                'banner_image_path' => $e->banner_image_path,
            ])->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
