<?php

declare(strict_types=1);

namespace App\Services\Storefront\Search;

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Services\Storefront\Search\Contracts\SearchProvider;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meilisearch backend. Indexes a flat document per Event into the
 * `events` index; `search()` hits Meili and rehydrates the matching
 * Event rows for the paginator response.
 *
 * Requires:
 *   - composer require meilisearch/meilisearch-php (or use the bare
 *     HTTP API as we do here)
 *   - MEILISEARCH_URL + MEILISEARCH_KEY in env
 *
 * When Meili is unreachable, falls back to DatabaseSearchProvider
 * so a search outage degrades to "slower" rather than "broken".
 */
class MeilisearchSearchProvider implements SearchProvider
{
    public function __construct(
        protected string $url,
        protected ?string $masterKey,
        protected string $indexName,
        protected DatabaseSearchProvider $fallback,
    ) {}

    public function search(array $filters): LengthAwarePaginator
    {
        $term = trim((string) ($filters['search'] ?? ''));
        if ($term === '') {
            // No keyword — Meili isn't useful; let the DB do the
            // facet filtering directly.
            return $this->fallback->search($filters);
        }

        try {
            $perPage = (int) ($filters['per_page'] ?? 20);
            $body = [
                'q' => $term,
                'limit' => $perPage,
                'attributesToRetrieve' => ['id'],
                'filter' => $this->buildFilters($filters),
            ];

            $response = Http::withHeaders($this->headers())
                ->timeout(5)
                ->post($this->url.'/indexes/'.$this->indexName.'/search', $body);

            if (! $response->successful()) {
                throw new \RuntimeException('Meilisearch returned '.$response->status());
            }

            $hits = collect((array) ($response->json('hits') ?? []))
                ->pluck('id')->filter()->values()->all();

            if (empty($hits)) {
                return $this->emptyPaginator($perPage);
            }

            $events = Event::query()
                ->whereIn('id', $hits)
                ->whereIn('status', $this->publicStatusValues())
                ->where('visibility', EventVisibility::Public->value)
                ->orderByRaw('FIELD(id, '.implode(',', array_map('intval', $hits)).')')
                ->get();

            return new LengthAwarePaginator(
                items: $events,
                total: (int) ($response->json('estimatedTotalHits') ?? $events->count()),
                perPage: $perPage,
                currentPage: 1,
            );
        } catch (\Throwable $e) {
            Log::warning('search.meilisearch.failed', ['error' => $e->getMessage()]);

            return $this->fallback->search($filters);
        }
    }

    public function index(int $eventId): void
    {
        $event = Event::query()->find($eventId);
        if (! $event) {
            return;
        }

        try {
            Http::withHeaders($this->headers())
                ->timeout(5)
                ->post($this->url.'/indexes/'.$this->indexName.'/documents', [[
                    'id' => $event->id,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'description' => $event->description,
                    'venue_name' => $event->venue_name,
                    'city' => $event->city,
                    'country_code' => $event->country_code,
                    'starts_at' => optional($event->starts_at)->timestamp,
                    'is_public' => $event->status?->isPubliclyVisible() === true
                        && $event->visibility?->value === EventVisibility::Public->value,
                    'organisation_id' => $event->organisation_id,
                ]]);
        } catch (\Throwable $e) {
            Log::warning('search.meilisearch.index_failed', ['error' => $e->getMessage(), 'event_id' => $eventId]);
        }
    }

    public function deindex(int $eventId): void
    {
        try {
            Http::withHeaders($this->headers())
                ->timeout(5)
                ->delete($this->url.'/indexes/'.$this->indexName.'/documents/'.$eventId);
        } catch (\Throwable $e) {
            Log::warning('search.meilisearch.deindex_failed', ['error' => $e->getMessage(), 'event_id' => $eventId]);
        }
    }

    protected function headers(): array
    {
        return $this->masterKey
            ? ['Authorization' => 'Bearer '.$this->masterKey, 'Content-Type' => 'application/json']
            : ['Content-Type' => 'application/json'];
    }

    /** @return list<string> */
    protected function buildFilters(array $filters): array
    {
        $f = ['is_public = true'];
        if (! empty($filters['city'])) {
            $f[] = 'city = "'.addslashes((string) $filters['city']).'"';
        }
        if (! empty($filters['country'])) {
            $f[] = 'country_code = "'.strtoupper((string) $filters['country']).'"';
        }

        return $f;
    }

    /** @return list<string> */
    protected function publicStatusValues(): array
    {
        return array_map(
            fn (EventStatus $s) => $s->value,
            array_filter(EventStatus::cases(), fn ($s) => $s->isPubliclyVisible())
        );
    }

    protected function emptyPaginator(int $perPage): LengthAwarePaginator
    {
        return new LengthAwarePaginator(collect(), 0, $perPage, 1);
    }
}
