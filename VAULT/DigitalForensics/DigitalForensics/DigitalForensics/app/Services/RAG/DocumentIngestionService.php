<?php

declare(strict_types=1);

namespace App\Services\RAG;

use InvalidArgumentException;

class DocumentIngestionService
{
    /**
     * @param  array<string, mixed>  $metadata
     * @return list<DocumentChunk>
     */
    public function ingest(
        string $title,
        string $path,
        string $content,
        array $metadata = [],
        ?string $sourceId = null,
        ?int $chunkSizeWords = null,
        ?int $chunkOverlapWords = null,
    ): array {
        $title = trim($title);
        $path = trim($path);
        $content = $this->normalizeText($content);

        if ($title === '') {
            throw new InvalidArgumentException('Document title is required.');
        }

        if ($content === '') {
            throw new InvalidArgumentException('Document content is required.');
        }

        $sourceId ??= $this->sourceId($title, $path);
        $chunkSizeWords ??= $this->configInt('rag.chunk_size_words', 180);
        $chunkOverlapWords ??= $this->configInt('rag.chunk_overlap_words', 30);
        $chunkSizeWords = max(1, $chunkSizeWords);
        $chunkOverlapWords = min(max(0, $chunkOverlapWords), $chunkSizeWords - 1);

        $words = preg_split('/\s+/', $content, flags: PREG_SPLIT_NO_EMPTY) ?: [];
        $step = max(1, $chunkSizeWords - $chunkOverlapWords);
        $chunks = [];

        for ($offset = 0, $index = 0; $offset < count($words); $offset += $step, $index++) {
            $chunkText = implode(' ', array_slice($words, $offset, $chunkSizeWords));

            if ($chunkText === '') {
                continue;
            }

            $chunks[] = new DocumentChunk(
                sourceId: $sourceId,
                chunkId: "{$sourceId}:{$index}",
                title: $title,
                path: $path,
                content: $chunkText,
                excerpt: $this->excerpt($chunkText),
                metadata: [
                    ...$metadata,
                    'chunk_size_words' => $chunkSizeWords,
                    'chunk_overlap_words' => $chunkOverlapWords,
                ],
                chunkIndex: $index,
            );

            if ($offset + $chunkSizeWords >= count($words)) {
                break;
            }
        }

        return $chunks;
    }

    private function sourceId(string $title, string $path): string
    {
        return 'src_'.substr(hash('sha256', $title.'|'.$path), 0, 16);
    }

    private function normalizeText(string $content): string
    {
        return trim(preg_replace('/\s+/', ' ', $content) ?? '');
    }

    private function excerpt(string $content): string
    {
        if (strlen($content) <= 280) {
            return $content;
        }

        return substr($content, 0, 277).'...';
    }

    private function configInt(string $key, int $default): int
    {
        try {
            return (int) config($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}
