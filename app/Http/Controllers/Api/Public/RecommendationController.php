<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Services\Storefront\PublicEventQuery;
use App\Services\Storefront\Search\Contracts\RecommendationStrategy;
use Illuminate\Http\JsonResponse;

/**
 *   GET /api/v1/public/events/{slug}/recommendations
 *
 * Returns up to N similar upcoming events for the "you might also
 * like" rail on the detail page.
 */
class RecommendationController extends Controller
{
    public function __construct(
        protected PublicEventQuery $query,
        protected RecommendationStrategy $strategy,
    ) {}

    public function index(string $slug): JsonResponse
    {
        $event = $this->query->findPublic($slug);
        if (! $event) {
            return response()->json(['error' => 'event_not_found'], 404);
        }

        return response()->json([
            'data' => $this->strategy->for($event, 6)
                ->map(fn ($e) => [
                    'slug' => $e->slug,
                    'name' => $e->name,
                    'starts_at' => optional($e->starts_at)->toIso8601String(),
                    'city' => $e->city,
                    'country_code' => $e->country_code,
                    'banner_image_path' => $e->banner_image_path,
                    'is_sold_out' => $e->isSoldOut(),
                ])->all(),
        ]);
    }
}
