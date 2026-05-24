<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventPrediction;
use Illuminate\Http\JsonResponse;

/**
 *   GET /api/v1/public/events/{slug}/predictions
 *
 * Surfaces the latest persisted sellout forecast. Public — useful
 * for "act fast, predicted sellout in 3 days" UX prompts. Returns
 * the confidence so the FE can render or suppress the badge.
 */
class EventPredictionController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $event = Event::query()->where('slug', $slug)->first();
        if (! $event) {
            return response()->json(['error' => 'event_not_found'], 404);
        }

        $rows = EventPrediction::query()
            ->where('event_id', $event->id)
            ->get(['prediction_type', 'value', 'confidence', 'computed_at', 'model_version']);

        return response()->json([
            'data' => $rows->map(fn (EventPrediction $p) => [
                'type' => $p->prediction_type,
                'value' => $p->value,
                'confidence' => $p->confidence !== null ? (float) $p->confidence : null,
                'computed_at' => optional($p->computed_at)->toIso8601String(),
                'model_version' => $p->model_version,
            ])->all(),
        ]);
    }
}
