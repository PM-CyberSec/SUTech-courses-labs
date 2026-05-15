<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\LLM\LLMRequest;
use App\Services\LLM\LocalStubLLMAdapter;
use App\Services\RAG\ProvenanceResponseSchema;
use PHPUnit\Framework\TestCase;

class MissingEvidenceConfidenceTest extends TestCase
{
    public function test_missing_evidence_defaults_to_low_confidence_source(): void
    {
        $request = LLMRequest::user(
            prompt: 'Can this event be explained from local evidence?',
            responseSchema: ProvenanceResponseSchema::schema(),
            evidence: [],
        );

        $response = (new LocalStubLLMAdapter)->complete($request);
        $data = $response->requireData();

        $this->assertLessThanOrEqual(0.25, $data['confidence']);
        $this->assertSame('missing-evidence', $data['sources'][0]['source_id']);
        $this->assertSame('No retrieved evidence', $data['sources'][0]['source_title']);
        $this->assertLessThanOrEqual(0.25, $data['sources'][0]['confidence']);
        $this->assertStringContainsString('No matching local evidence', $data['sources'][0]['excerpt']);
        $this->assertStringContainsString('Retrieved evidence for this response:', $request->messages[0]['content']);
    }
}
