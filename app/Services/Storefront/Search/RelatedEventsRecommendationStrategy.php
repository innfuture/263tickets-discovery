<?php

declare(strict_types=1);

namespace App\Services\Storefront\Search;

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Services\Storefront\Search\Contracts\RecommendationStrategy;
use Illuminate\Support\Collection;

/**
 * Default recommendations — runs three passes in order, dedup'd, then
 * truncated to `$limit`:
 *
 *   1. Same organizer, upcoming, excluding self
 *   2. Same category + same city
 *   3. Same city, any category
 *
 * Cheap to compute, no extra schema. Swap with an Algolia Recommend
 * binding for collaborative filtering once enough buyer history exists.
 */
class RelatedEventsRecommendationStrategy implements RecommendationStrategy
{
    public function for(Event $event, int $limit = 6): Collection
    {
        $visibleStatuses = array_map(
            fn (EventStatus $s) => $s->value,
            array_filter(EventStatus::cases(), fn ($s) => $s->isPubliclyVisible())
        );

        $base = fn () => Event::query()
            ->whereIn('status', $visibleStatuses)
            ->where('visibility', EventVisibility::Public->value)
            ->where('id', '!=', $event->id)
            ->where('starts_at', '>=', now());

        $sameOrg = $base()->where('organisation_id', $event->organisation_id)
            ->orderBy('starts_at')->limit($limit)->get();

        $sameCat = $event->category_id ? $base()
            ->where('category_id', $event->category_id)
            ->where('city', $event->city)
            ->orderBy('starts_at')->limit($limit)->get() : collect();

        $sameCity = $base()->where('city', $event->city)
            ->orderBy('starts_at')->limit($limit)->get();

        return $sameOrg
            ->concat($sameCat)
            ->concat($sameCity)
            ->unique('id')
            ->take($limit)
            ->values();
    }
}
