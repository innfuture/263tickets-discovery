<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Developer;

use App\Http\Controllers\Controller;
use App\Services\Analytics\DifferentialPrivacyAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 *   GET /api/developer/v1/analytics/cross-org
 *      ?category_id=…&country=…&months_back=1
 *
 * Returns Laplace-noised aggregates so any individual organizer's
 * numbers stay private. Requires the `analytics.read` scope —
 * Enterprise+ tier.
 *
 * `noisy=false` means the group was too small to safely report
 * (default cutoff: 10 organizations) and the metrics are null.
 */
class CrossOrgAnalyticsController extends Controller
{
    public function __construct(protected DifferentialPrivacyAnalytics $dp) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['nullable', 'integer'],
            'country' => ['nullable', 'string', 'size:2'],
            'months_back' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        return response()->json([
            'data' => $this->dp->categoryAverages(
                categoryId: $validated['category_id'] ?? null,
                countryCode: $validated['country'] ?? null,
                monthsBack: $validated['months_back'] ?? 1,
            ),
        ]);
    }
}
