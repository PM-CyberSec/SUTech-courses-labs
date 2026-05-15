<?php

declare(strict_types=1);

namespace App\Services\Evaluation;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class PromptVersionMetadata
{
    public readonly string $updatedAt;

    public function __construct(
        public readonly string $promptName,
        public readonly string $version,
        public readonly string $description,
        public readonly string $schemaVersion,
        string $updatedAt,
    ) {
        foreach ([
            'prompt_name' => $promptName,
            'version' => $version,
            'description' => $description,
            'schema_version' => $schemaVersion,
            'updated_at' => $updatedAt,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("Prompt metadata field {$field} is required.");
            }
        }

        if (! preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            throw new InvalidArgumentException('Prompt metadata version must use semantic version format.');
        }

        if (! preg_match('/^\d+\.\d+$/', $schemaVersion)) {
            throw new InvalidArgumentException('Prompt metadata schema_version must use major.minor format.');
        }

        try {
            $this->updatedAt = CarbonImmutable::parse($updatedAt)->utc()->toISOString();
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException('Prompt metadata updated_at must be a valid datetime.', previous: $exception);
        }
    }

    /**
     * @return array{prompt_name: string, version: string, description: string, schema_version: string, updated_at: string}
     */
    public function toArray(): array
    {
        return [
            'prompt_name' => $this->promptName,
            'version' => $this->version,
            'description' => $this->description,
            'schema_version' => $this->schemaVersion,
            'updated_at' => $this->updatedAt,
        ];
    }
}
