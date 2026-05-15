<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RAG\ProvenanceResponseSchema;
use PHPUnit\Framework\TestCase;

class ProvenanceSchemaTest extends TestCase
{
    public function test_provenance_response_schema_requires_source_fields(): void
    {
        $schema = ProvenanceResponseSchema::schema();
        $required = $schema['properties']['sources']['items']['required'];

        $this->assertSame([
            'source_id',
            'source_title',
            'excerpt',
            'confidence',
            'retrieved_at',
        ], $required);
    }
}
