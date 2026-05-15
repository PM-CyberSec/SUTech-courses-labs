<?php

declare(strict_types=1);

namespace App\Services\LLM;

use App\Services\RAG\ProvenanceEvidence;
use App\Services\RAG\RagPromptBuilder;
use InvalidArgumentException;

final class LLMRequest
{
    /**
     * @var list<array{role: string, content: string}>
     */
    public readonly array $messages;

    /**
     * @var array<string, mixed>
     */
    public readonly array $metadata;

    /**
     * @var list<array{source_id: string, source_title: string, excerpt: string, confidence: float, retrieved_at: string}>
     */
    public readonly array $evidence;

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>|null  $responseSchema
     * @param  array<string, mixed>  $metadata
     * @param  array<int, ProvenanceEvidence|array<string, mixed>>|null  $evidence
     */
    public function __construct(
        array $messages,
        public readonly ?string $model = null,
        public readonly float $temperature = 0.0,
        public readonly ?array $responseSchema = null,
        public readonly bool $expectsJson = false,
        array $metadata = [],
        ?array $evidence = null,
    ) {
        if ($messages === []) {
            throw new InvalidArgumentException('LLM requests must include at least one message.');
        }

        foreach ($messages as $message) {
            if (! isset($message['role'], $message['content'])) {
                throw new InvalidArgumentException('Each LLM message must include role and content.');
            }

            if (! is_string($message['role']) || trim($message['role']) === '') {
                throw new InvalidArgumentException('LLM message roles must be non-empty strings.');
            }

            if (! is_string($message['content'])) {
                throw new InvalidArgumentException('LLM message content must be a string.');
            }
        }

        $this->metadata = $metadata;
        $this->evidence = ProvenanceEvidence::normalizeList($evidence);
        $this->messages = RagPromptBuilder::injectEvidence($messages, $this->evidence);
    }

    /**
     * @param  array<string, mixed>|null  $responseSchema
     * @param  array<int, ProvenanceEvidence|array<string, mixed>>|null  $evidence
     */
    public static function user(
        string $prompt,
        ?array $responseSchema = null,
        ?string $model = null,
        bool $expectsJson = false,
        ?array $evidence = null,
    ): self {
        return new self(
            messages: [['role' => 'user', 'content' => $prompt]],
            model: $model,
            responseSchema: $responseSchema,
            expectsJson: $expectsJson || $responseSchema !== null,
            evidence: $evidence,
        );
    }

    /**
     * @param  array<int, ProvenanceEvidence|array<string, mixed>>  $evidence
     */
    public function withEvidence(array $evidence): self
    {
        return new self(
            messages: $this->baseMessages(),
            model: $this->model,
            temperature: $this->temperature,
            responseSchema: $this->responseSchema,
            expectsJson: $this->expectsJson,
            metadata: $this->metadata,
            evidence: $evidence,
        );
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function baseMessages(): array
    {
        if ($this->evidence === []) {
            return $this->messages;
        }

        return array_values(array_filter(
            $this->messages,
            static fn (array $message): bool => ! (
                ($message['role'] ?? null) === 'system'
                && str_starts_with($message['content'] ?? '', 'Retrieved evidence for this response:')
            ),
        ));
    }
}
