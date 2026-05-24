<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Services\Ai\Contracts\EmbeddingClient;

/**
 * Deterministic hash-based fake. NOT a real embedding — semantically
 * similar inputs do NOT produce similar vectors. Suitable for dev,
 * tests, and verifying the indexing/search pipeline end-to-end
 * without an external API.
 *
 * Default `dimensions()` is 64 so the JSON column stays small for
 * dev DBs.
 */
class StubEmbeddingClient implements EmbeddingClient
{
    public function __construct(protected int $dims = 64) {}

    public function model(): string
    {
        return 'stub-hash-v1';
    }

    public function dimensions(): int
    {
        return $this->dims;
    }

    public function embed(string $text): array
    {
        $hash = hash('sha512', $text, true); // 64 raw bytes
        $vec = [];
        for ($i = 0; $i < $this->dims; $i++) {
            // Project each byte into [-1, 1].
            $byte = ord($hash[$i % strlen($hash)]);
            $vec[] = ($byte - 128) / 128.0;
        }

        return $vec;
    }
}
