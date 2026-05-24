<?php

declare(strict_types=1);

namespace App\Services\Storefront\Search;

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\EventEmbedding;
use App\Services\Ai\Contracts\EmbeddingClient;
use App\Services\Storefront\Search\Contracts\SearchProvider;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

/**
 * Vector search. When a keyword `search` filter is provided, the
 * query is embedded via the configured EmbeddingClient and ranked
 * against `event_embeddings` rows by cosine similarity.
 *
 * Two execution paths:
 *
 *   1. Postgres + pgvector       → SQL `embedding <=> :query` ORDER
 *      (preferred, sub-millisecond). This impl is JSON-array based
 *      since we can't assume pgvector is installed; the DB pulls
 *      every matching row + we rank in PHP.
 *
 *   2. JSON-array fallback       → SELECT all candidates, compute
 *      cosine similarity in-process. Fine up to ~10k events; beyond
 *      that, install pgvector.
 *
 * For non-keyword queries (city / country filters only) we delegate
 * to `DatabaseSearchProvider` — vector search doesn't help there.
 */
class PgvectorSearchProvider implements SearchProvider
{
    public function __construct(
        protected EmbeddingClient $embeddings,
        protected DatabaseSearchProvider $fallback,
        protected int $candidateLimit = 1000,
    ) {}

    public function search(array $filters): LengthAwarePaginator
    {
        $term = trim((string) ($filters['search'] ?? ''));
        if ($term === '') {
            return $this->fallback->search($filters);
        }

        try {
            $queryVec = $this->embeddings->embed($term);
        } catch (\Throwable $e) {
            Log::warning('vector.search.query_embed_failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->fallback->search($filters);
        }

        $rows = EventEmbedding::query()
            ->where('model', $this->embeddings->model())
            ->whereHas('event', fn ($q) => $q
                ->whereIn('status', $this->publicStatuses())
                ->where('visibility', EventVisibility::Public->value)
            )
            ->with('event:id,name,slug,short_description,starts_at,city,country_code,is_featured,banner_image_path,tickets_sold_count,capacity')
            ->limit($this->candidateLimit)
            ->get();

        $ranked = $rows
            ->map(function (EventEmbedding $row) use ($queryVec) {
                return [
                    'event' => $row->event,
                    'score' => $this->cosineSimilarity($queryVec, (array) $row->embedding),
                ];
            })
            ->filter(fn ($r) => $r['event'] !== null)
            ->sortByDesc('score')
            ->values();

        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);
        $offset = ($page - 1) * $perPage;

        $sliced = $ranked->slice($offset, $perPage)->pluck('event')->values();

        return new LengthAwarePaginator(
            items: $sliced,
            total: $ranked->count(),
            perPage: $perPage,
            currentPage: $page,
        );
    }

    public function index(int $eventId): void
    {
        $event = Event::query()->find($eventId);
        if (! $event) {
            return;
        }
        app(\App\Services\Ai\VectorIndexer::class)->indexEvent($event);
    }

    public function deindex(int $eventId): void
    {
        app(\App\Services\Ai\VectorIndexer::class)->deindexEvent($eventId);
    }

    /** Standard cosine sim. Both vectors must be the same dimension. */
    protected function cosineSimilarity(array $a, array $b): float
    {
        $dim = min(count($a), count($b));
        if ($dim === 0) {
            return 0.0;
        }
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        for ($i = 0; $i < $dim; $i++) {
            $av = (float) $a[$i];
            $bv = (float) $b[$i];
            $dot += $av * $bv;
            $na += $av * $av;
            $nb += $bv * $bv;
        }
        $denom = sqrt($na) * sqrt($nb);

        return $denom > 0 ? $dot / $denom : 0.0;
    }

    /** @return list<string> */
    protected function publicStatuses(): array
    {
        return array_map(
            fn (EventStatus $s) => $s->value,
            array_filter(EventStatus::cases(), fn ($s) => $s->isPubliclyVisible())
        );
    }
}
