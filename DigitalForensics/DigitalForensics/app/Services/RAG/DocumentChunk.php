<?php

declare(strict_types=1);

namespace App\Services\RAG;

final class DocumentChunk
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $sourceId,
        public readonly string $chunkId,
        public readonly string $title,
        public readonly string $path,
        public readonly string $content,
        public readonly string $excerpt,
        public readonly array $metadata = [],
        public readonly int $chunkIndex = 0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_id' => $this->sourceId,
            'chunk_id' => $this->chunkId,
            'title' => $this->title,
            'path' => $this->path,
            'content' => $this->content,
            'excerpt' => $this->excerpt,
            'metadata' => $this->metadata,
            'chunk_index' => $this->chunkIndex,
        ];
    }
}
