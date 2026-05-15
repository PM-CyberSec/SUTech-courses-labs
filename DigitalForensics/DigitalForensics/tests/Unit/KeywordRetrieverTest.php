<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RAG\DocumentIngestionService;
use App\Services\RAG\LocalKeywordRetriever;
use PHPUnit\Framework\TestCase;

class KeywordRetrieverTest extends TestCase
{
    public function test_keyword_retriever_returns_matching_provenance_evidence(): void
    {
        $ingestion = new DocumentIngestionService;
        $chunks = [
            ...$ingestion->ingest(
                title: 'Suricata Triage',
                path: 'docs/suricata.md',
                content: 'Suricata detected exfiltration over TLS with unusual outbound bytes.',
                sourceId: 'src_suricata',
            ),
            ...$ingestion->ingest(
                title: 'Login Runbook',
                path: 'docs/login.md',
                content: 'Failed login lockouts require identity provider review.',
                sourceId: 'src_login',
            ),
        ];

        $results = (new LocalKeywordRetriever($chunks))->retrieve('suricata exfiltration', limit: 1);

        $this->assertCount(1, $results);
        $source = $results[0]->toArray();

        $this->assertSame('src_suricata', $source['source_id']);
        $this->assertSame('Suricata Triage', $source['source_title']);
        $this->assertStringContainsString('exfiltration', $source['excerpt']);
        $this->assertGreaterThan(0.5, $source['confidence']);
        $this->assertNotEmpty($source['retrieved_at']);
    }
}
