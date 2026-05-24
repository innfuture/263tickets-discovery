<?php

declare(strict_types=1);

namespace App\Services\Storefront\Search;

use App\Services\Storefront\PublicEventQuery;
use App\Services\Storefront\Search\Contracts\SearchProvider;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Default SearchProvider — delegates to the existing
 * `PublicEventQuery::paginate()` which uses SQL `LIKE`. Index /
 * de-index are no-ops since the DB is always the source of truth.
 *
 * Suitable up to ~10k events; beyond that, switch to Meilisearch.
 */
class DatabaseSearchProvider implements SearchProvider
{
    public function __construct(protected PublicEventQuery $query) {}

    public function search(array $filters): LengthAwarePaginator
    {
        return $this->query->paginate(
            search: $filters['search'] ?? null,
            city: $filters['city'] ?? null,
            countryCode: $filters['country'] ?? null,
            categorySlug: $filters['category'] ?? null,
            organizerSlug: $filters['organizer'] ?? null,
            startsAfter: $filters['starts_after'] ?? null,
            startsBefore: $filters['starts_before'] ?? null,
            sort: $filters['sort'] ?? 'starts_at',
            perPage: (int) ($filters['per_page'] ?? 20),
        );
    }

    public function index(int $eventId): void
    {
        // DB-backed search has nothing to index.
    }

    public function deindex(int $eventId): void
    {
        // ditto.
    }
}
