<?php

declare(strict_types=1);

namespace App\Services\RAG;

use Carbon\CarbonImmutable;

final class ProvenanceEvidence
{
    public const LOW_CONFIDENCE = 0.10;

    public function __construct(
        public readonly string $sourceId,
        public readonly string $sourceTitle,
        public readonly string $excerpt,
        public readonly float $confidence,
        public readonly string $retrievedAt,
    ) {}

    public static function fromChunk(DocumentChunk $chunk, float $confidence, ?CarbonImmutable $retrievedAt = null): self
    {
        return new self(
            sourceId: $chunk->sourceId,
            sourceTitle: $chunk->title,
            excerpt: $chunk->excerpt,
            confidence: round(min(max($confidence, 0.0), 1.0), 4),
            retrievedAt: ($retrievedAt ?? CarbonImmutable::now('UTC'))->toISOString(),
        );
    }

    public static function missing(?CarbonImmutable $retrievedAt = null): self
    {
        return new self(
            sourceId: 'missing-evidence',
            sourceTitle: 'No retrieved evidence',
            excerpt: 'No matching local evidence was retrieved for this request.',
            confidence: self::LOW_CONFIDENCE,
            retrievedAt: ($retrievedAt ?? CarbonImmutable::now('UTC'))->toISOString(),
        );
    }

    /**
     * @param  array<int, ProvenanceEvidence|array<string, mixed>>|null  $evidence
     * @return list<array{source_id: string, source_title: string, excerpt: string, confidence: float, retrieved_at: string}>
     */
    public static function normalizeList(?array $evidence): array
    {
        if ($evidence === null) {
            return [];
        }

        if ($evidence === []) {
            return [self::missing()->toArray()];
        }

        $normalized = [];

        foreach ($evidence as $item) {
            if ($item instanceof self) {
                $normalized[] = $item->toArray();

                continue;
            }

            $normalized[] = [
                'source_id' => (string) ($item['source_id'] ?? 'unknown'),
                'source_title' => (string) ($item['source_title'] ?? 'Unknown source'),
                'excerpt' => (string) ($item['excerpt'] ?? ''),
                'confidence' => round(min(max((float) ($item['confidence'] ?? self::LOW_CONFIDENCE), 0.0), 1.0), 4),
                'retrieved_at' => (string) ($item['retrieved_at'] ?? CarbonImmutable::now('UTC')->toISOString()),
            ];
        }

        return $normalized;
    }

    /**
     * @return array{source_id: string, source_title: string, excerpt: string, confidence: float, retrieved_at: string}
     */
    public function toArray(): array
    {
        return [
            'source_id' => $this->sourceId,
            'source_title' => $this->sourceTitle,
            'excerpt' => $this->excerpt,
            'confidence' => $this->confidence,
            'retrieved_at' => $this->retrievedAt,
        ];
    }
}
