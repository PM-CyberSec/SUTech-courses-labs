<?php

declare(strict_types=1);

namespace App\Services\LLM;

final class LLMResponse
{
    /**
     * @param  array<string, mixed>|null  $data
     * @param  array<string, mixed>|null  $usage
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $content,
        public readonly ?array $data = null,
        public readonly ?string $model = null,
        public readonly ?string $finishReason = null,
        public readonly ?array $usage = null,
        public readonly array $raw = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function requireData(): array
    {
        if ($this->data === null) {
            throw new LLMAdapterException('The LLM response did not contain structured data.');
        }

        return $this->data;
    }
}
