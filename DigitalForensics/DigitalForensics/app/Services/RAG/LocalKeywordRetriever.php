<?php

declare(strict_types=1);

namespace App\Services\RAG;

use Carbon\CarbonImmutable;

class LocalKeywordRetriever implements RetrieverInterface
{
    /**
     * @param  list<DocumentChunk>  $chunks
     */
    public function __construct(private array $chunks = []) {}

    /**
     * @param  list<DocumentChunk>  $chunks
     */
    public function setChunks(array $chunks): void
    {
        $this->chunks = array_values($chunks);
    }

    /**
     * @param  list<DocumentChunk>  $chunks
     */
    public function addChunks(array $chunks): void
    {
        array_push($this->chunks, ...$chunks);
    }

    /**
     * @return list<ProvenanceEvidence>
     */
    public function retrieve(string $query, int $limit = 5): array
    {
        $terms = $this->terms($query);
        if ($terms === [] || $limit < 1) {
            return [];
        }

        $retrievedAt = CarbonImmutable::now('UTC');
        $scored = [];

        foreach ($this->chunks as $chunk) {
            $score = $this->score($chunk, $terms);

            if ($score <= 0) {
                continue;
            }

            $scored[] = [
                'chunk' => $chunk,
                'score' => $score,
                'confidence' => min(1.0, $score / count($terms)),
            ];
        }

        usort(
            $scored,
            static fn (array $left, array $right): int => $right['score'] <=> $left['score'],
        );

        return array_map(
            static fn (array $item): ProvenanceEvidence => ProvenanceEvidence::fromChunk(
                $item['chunk'],
                $item['confidence'],
                $retrievedAt,
            ),
            array_slice($scored, 0, $limit),
        );
    }

    /**
     * @param  list<string>  $terms
     */
    private function score(DocumentChunk $chunk, array $terms): int
    {
        $haystack = strtolower($chunk->title.' '.$chunk->path.' '.$chunk->excerpt.' '.$chunk->content);
        $score = 0;

        foreach ($terms as $term) {
            if (str_contains($haystack, $term)) {
                $score++;
            }
        }

        return $score;
    }

    /**
     * @return list<string>
     */
    private function terms(string $query): array
    {
        $tokens = preg_split('/[^a-z0-9_]+/i', strtolower($query), flags: PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_filter(
            $tokens,
            static fn (string $token): bool => strlen($token) >= 3,
        );

        return array_values(array_unique($tokens));
    }
}
