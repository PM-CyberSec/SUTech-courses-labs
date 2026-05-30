<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RAG\DocumentIngestionService;
use PHPUnit\Framework\TestCase;

class DocumentChunkingTest extends TestCase
{
    public function test_document_ingestion_chunks_text_and_preserves_source_metadata(): void
    {
        $chunks = (new DocumentIngestionService)->ingest(
            title: 'Incident Runbook',
            path: 'docs/runbook.md',
            content: 'alpha beta gamma delta epsilon zeta eta theta iota kappa lambda',
            metadata: ['owner' => 'soc'],
            sourceId: 'src_runbook',
            chunkSizeWords: 4,
            chunkOverlapWords: 1,
        );

        $this->assertCount(4, $chunks);
        $this->assertSame('src_runbook', $chunks[0]->sourceId);
        $this->assertSame('src_runbook:0', $chunks[0]->chunkId);
        $this->assertSame('Incident Runbook', $chunks[0]->title);
        $this->assertSame('docs/runbook.md', $chunks[0]->path);
        $this->assertSame('alpha beta gamma delta', $chunks[0]->excerpt);
        $this->assertSame('soc', $chunks[0]->metadata['owner']);
        $this->assertSame(4, $chunks[0]->metadata['chunk_size_words']);
        $this->assertSame('delta epsilon zeta eta', $chunks[1]->content);
    }
}
