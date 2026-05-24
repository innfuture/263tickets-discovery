<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Event;
use App\Models\EventEmbedding;
use App\Services\Ai\Contracts\EmbeddingClient;
use Illuminate\Support\Facades\Log;

/**
 * Materialises / refreshes `event_embeddings` rows. Called by the
 * EventObserver on save, and by a back-fill artisan command after
 * swapping the embedding model.
 *
 * Skips work if the event's source text hash matches the existing
 * row — re-embedding 100k events on every save is wasteful when only
 * a tier was tweaked.
 */
class VectorIndexer
{
    public function __construct(protected EmbeddingClient $client) {}

    public function indexEvent(Event $event): ?EventEmbedding
    {
        $text = $this->buildIndexText($event);
        $hash = hash('sha256', $text);

        $existing = EventEmbedding::query()
            ->where('event_id', $event->id)
            ->where('model', $this->client->model())
            ->first();

        if ($existing && $existing->content_hash === $hash) {
            return $existing; // No change since last embed.
        }

        try {
            $vec = $this->client->embed($text);
        } catch (\Throwable $e) {
            Log::warning('vector.indexer.embed_failed', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($existing) {
            $existing->forceFill([
                'embedding' => $vec,
                'content_hash' => $hash,
                'generated_at' => now(),
            ])->save();

            return $existing->fresh();
        }

        return EventEmbedding::create([
            'event_id' => $event->id,
            'model' => $this->client->model(),
            'embedding' => $vec,
            'content_hash' => $hash,
            'generated_at' => now(),
        ]);
    }

    public function deindexEvent(int $eventId): void
    {
        EventEmbedding::query()->where('event_id', $eventId)->delete();
    }

    /**
     * The text we embed. Concatenation order is stable so the hash
     * is meaningful — a venue rename triggers re-embed; reordering
     * lineup artists doesn't.
     */
    protected function buildIndexText(Event $event): string
    {
        $parts = [
            $event->name,
            $event->short_description,
            $event->description,
            $event->venue_name,
            $event->city,
            $event->country_code,
            $event->category?->name,
            implode(', ', (array) ($event->tags ?? [])),
        ];

        return trim(implode("\n", array_filter($parts, fn ($v) => is_string($v) && $v !== '')));
    }
}
