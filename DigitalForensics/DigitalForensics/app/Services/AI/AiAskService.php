<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Evaluation\EvaluationCase;
use App\Services\Evaluation\LLMOutputEvaluator;
use App\Services\LLM\LLMAdapter;
use App\Services\LLM\LLMRequest;
use App\Services\RAG\DocumentIngestionService;
use App\Services\RAG\LocalKeywordRetriever;
use App\Services\RAG\ProvenanceEvidence;
use App\Services\RAG\ProvenanceResponseSchema;
use App\Services\RAG\RetrieverInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiAskService
{
    public function __construct(
        private readonly RetrieverInterface $retriever,
        private readonly DocumentIngestionService $documentIngestion,
        private readonly LLMAdapter $llm,
        private readonly LLMOutputEvaluator $evaluator,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{answer: string, confidence: float, sources: list<array{source_id: string, source_title: string, excerpt: string, confidence: float, retrieved_at: string}>}
     */
    public function ask(?User $user, array $payload): array
    {
        $question = trim((string) $payload['question']);
        $context = trim((string) ($payload['context'] ?? ''));
        $evidence = $this->retrieveEvidence(
            question: $question,
            context: $context,
            contextTitle: trim((string) ($payload['context_title'] ?? 'Request context')),
            contextPath: trim((string) ($payload['context_path'] ?? 'request://context')),
        );

        try {
            $llmRequest = LLMRequest::user(
                prompt: $this->prompt($question),
                responseSchema: ProvenanceResponseSchema::schema(),
                evidence: $evidence,
            );

            $response = $this->llm->complete($llmRequest);
            $result = $this->structuredResult($response->requireData(), $llmRequest->evidence);
            $evaluation = $this->evaluator->evaluate(new EvaluationCase(
                expectedAnswer: $result['answer'],
                actualAnswer: $result['answer'],
                confidence: $result['confidence'],
                sources: $result['sources'],
                expectedSourceIds: array_values(array_map(
                    static fn (array $source): string => $source['source_id'],
                    $result['sources'],
                )),
            ));

            Log::info('AI ask evaluation completed', [
                'event_type' => 'ai.ask.evaluation_completed',
                'user_id' => $user?->id,
                'passed' => $evaluation->passed,
                'reasons' => $evaluation->reasons,
                'confidence' => $evaluation->confidence,
                'source_coverage' => $evaluation->sourceCoverage,
            ]);

            $this->auditLogger->record('ai.ask', 'success', [
                'user_id' => $user?->id,
                'question' => Str::limit($question, 1000),
                'context_present' => $context !== '',
                'answer' => Str::limit($result['answer'], 1000),
                'confidence' => $result['confidence'],
                'source_ids' => array_map(
                    static fn (array $source): string => $source['source_id'],
                    $result['sources'],
                ),
                'evaluation' => $evaluation->toArray(),
            ]);

            return $result;
        } catch (\Throwable $exception) {
            $this->auditLogger->record('ai.ask', 'failure', [
                'user_id' => $user?->id,
                'question' => Str::limit($question, 1000),
                'context_present' => $context !== '',
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @return list<ProvenanceEvidence>
     */
    private function retrieveEvidence(
        string $question,
        string $context,
        string $contextTitle,
        string $contextPath,
    ): array {
        $retriever = $this->retriever;

        if ($context !== '') {
            $chunks = $this->documentIngestion->ingest(
                title: $contextTitle === '' ? 'Request context' : $contextTitle,
                path: $contextPath === '' ? 'request://context' : $contextPath,
                content: $context,
                metadata: ['scope' => 'request'],
                sourceId: 'request_context',
            );

            $retriever = new LocalKeywordRetriever($chunks);
        }

        return $retriever->retrieve($question, 5);
    }

    private function prompt(string $question): string
    {
        return implode("\n", [
            'Answer the user question using only the retrieved evidence.',
            'Return JSON that matches the provided schema.',
            'If the evidence is missing or weak, return low confidence.',
            "Question: {$question}",
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{source_id: string, source_title: string, excerpt: string, confidence: float, retrieved_at: string}>  $sources
     * @return array{answer: string, confidence: float, sources: list<array{source_id: string, source_title: string, excerpt: string, confidence: float, retrieved_at: string}>}
     */
    private function structuredResult(array $data, array $sources): array
    {
        $answer = trim((string) ($data['answer'] ?? $data['summary'] ?? ''));
        if ($answer === '') {
            $answer = 'No answer was generated.';
        }

        $modelConfidence = min(max((float) ($data['confidence'] ?? 0.0), 0.0), 1.0);
        $sourceConfidence = $sources === []
            ? 0.0
            : min(array_map(static fn (array $source): float => (float) $source['confidence'], $sources));

        return [
            'answer' => $answer,
            'confidence' => round(min($modelConfidence, $sourceConfidence), 4),
            'sources' => $sources,
        ];
    }
}
