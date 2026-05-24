<?php

declare(strict_types=1);

namespace App\Services\Storefront\Search\Contracts;

use App\Models\Event;
use Illuminate\Support\Collection;

/**
 * Pluggable "you might also like" engine. Default
 * `RelatedEventsRecommendationStrategy` is rules-based (same category
 * + same city); plug in a Algolia Recommend / collaborative-filter
 * implementation downstream without touching the controller.
 *
 * @return Collection<int, Event>
 */
interface RecommendationStrategy
{
    public function for(Event $event, int $limit = 6): Collection;
}
