<?php

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

/**
 * Generates a vector embedding for a piece of text. Used by the
 * VectorIndexer to materialise `event_embeddings` rows and by the
 * PgvectorSearchProvider to embed the user's query at search time.
 *
 * Default binding is `StubEmbeddingClient` (deterministic hash-based
 * fake — useful for dev + tests). Production binds an `OpenAiEmbeddingClient`
 * or `CohereEmbeddingClient` from env.
 */
interface EmbeddingClient
{
    /** Model identifier — written to event_embeddings.model. */
    public function model(): string;

    /** Dimensionality of returned vectors (e.g. 1536 for OpenAI). */
    public function dimensions(): int;

    /**
     * Embed a single document. Returns an array of `dimensions()`
     * floats. Throws on transient failure — caller decides retry.
     *
     * @return list<float>
     */
    public function embed(string $text): array;
}
