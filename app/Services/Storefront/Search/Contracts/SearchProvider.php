<?php

declare(strict_types=1);

namespace App\Services\Storefront\Search\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Pluggable event search. The default `DatabaseSearchProvider` runs
 * SQL `LIKE` against name / venue / description — fine for small
 * catalogs. Swap to `MeilisearchSearchProvider` once the index is
 * configured for typo tolerance + much better latency at scale.
 *
 * Returns the Eloquent paginator unchanged so downstream code
 * (controllers, sitemap, etc.) doesn't care about the backend.
 *
 * @phpstan-type SearchFilters array{
 *   search?: string|null,
 *   city?: string|null,
 *   country?: string|null,
 *   category?: string|null,
 *   organizer?: string|null,
 *   starts_after?: string|null,
 *   starts_before?: string|null,
 *   sort?: string|null,
 *   per_page?: int|null,
 * }
 */
interface SearchProvider
{
    /** @param SearchFilters $filters */
    public function search(array $filters): LengthAwarePaginator;

    /**
     * Push or update an event's indexed representation. Called by
     * `EventObserver::saved` so the index reflects organizer edits
     * within the cache-bust window.
     */
    public function index(int $eventId): void;

    /** Removal hook. */
    public function deindex(int $eventId): void;
}
