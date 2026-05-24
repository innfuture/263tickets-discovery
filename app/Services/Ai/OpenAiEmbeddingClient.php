<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Services\Ai\Contracts\EmbeddingClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Real OpenAI embeddings via the REST API. Set `OPENAI_API_KEY` in
 * env to activate. Falls back to throwing on misconfiguration — the
 * caller (VectorIndexer) catches and logs, so missing keys degrade
 * to "search runs DB-LIKE" rather than crashing.
 *
 * Default model: `text-embedding-3-small` (1536 dims) — cheaper +
 * faster than text-embedding-3-large for the event-discovery use
 * case where pinpoint accuracy isn't required.
 */
class OpenAiEmbeddingClient implements EmbeddingClient
{
    public function __construct(
        protected string $apiKey,
        protected string $model = 'text-embedding-3-small',
        protected int $dims = 1536,
        protected int $timeoutSeconds = 10,
    ) {}

    public function model(): string
    {
        return $this->model;
    }

    public function dimensions(): int
    {
        return $this->dims;
    }

    public function embed(string $text): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY not configured.');
        }

        $response = Http::withToken($this->apiKey)
            ->timeout($this->timeoutSeconds)
            ->post('https://api.openai.com/v1/embeddings', [
                'input' => $text,
                'model' => $this->model,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'OpenAI embeddings API returned '.$response->status().': '.$response->body(),
            );
        }

        $vector = $response->json('data.0.embedding');
        if (! is_array($vector) || count($vector) !== $this->dims) {
            throw new RuntimeException('Unexpected OpenAI embeddings response shape.');
        }

        return array_map('floatval', $vector);
    }
}
