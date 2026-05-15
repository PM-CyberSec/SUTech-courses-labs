<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Evaluation\PromptVersionMetadata;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PromptVersionMetadataTest extends TestCase
{
    public function test_prompt_version_metadata_serializes_valid_metadata(): void
    {
        $metadata = new PromptVersionMetadata(
            promptName: 'incident_summary',
            version: '1.0.0',
            description: 'Summarize incident evidence with provenance.',
            schemaVersion: '1.0',
            updatedAt: '2026-05-04T00:00:00Z',
        );

        $this->assertSame([
            'prompt_name' => 'incident_summary',
            'version' => '1.0.0',
            'description' => 'Summarize incident evidence with provenance.',
            'schema_version' => '1.0',
            'updated_at' => '2026-05-04T00:00:00.000000Z',
        ], $metadata->toArray());
    }

    public function test_prompt_version_metadata_rejects_invalid_version(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PromptVersionMetadata(
            promptName: 'incident_summary',
            version: 'v1',
            description: 'Invalid version fixture.',
            schemaVersion: '1.0',
            updatedAt: '2026-05-04T00:00:00Z',
        );
    }

    public function test_prompt_version_metadata_requires_fields(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PromptVersionMetadata(
            promptName: '',
            version: '1.0.0',
            description: 'Missing name fixture.',
            schemaVersion: '1.0',
            updatedAt: '2026-05-04T00:00:00Z',
        );
    }
}
