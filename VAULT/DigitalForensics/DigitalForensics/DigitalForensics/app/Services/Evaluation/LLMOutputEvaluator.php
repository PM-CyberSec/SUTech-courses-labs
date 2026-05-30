<?php

declare(strict_types=1);

namespace App\Services\Evaluation;

class LLMOutputEvaluator
{
    public function evaluate(EvaluationCase $case): EvaluationResult
    {
        $reasons = [];
        $answerMatches = str_contains(
            $this->normalize($case->actualAnswer),
            $this->normalize($case->expectedAnswer),
        );

        if (! $answerMatches) {
            $reasons[] = 'expected_answer_not_found';
        }

        if ($case->confidence < $case->minConfidence) {
            $reasons[] = 'confidence_below_threshold';
        }

        $sourceCoverage = $this->sourceCoverage($case->sources, $case->expectedSourceIds);
        if ($sourceCoverage < $case->minSourceCoverage) {
            $reasons[] = 'source_coverage_below_threshold';
        }

        return new EvaluationResult(
            expectedAnswer: $case->expectedAnswer,
            actualAnswer: $case->actualAnswer,
            confidence: round(min(max($case->confidence, 0.0), 1.0), 4),
            sourceCoverage: $sourceCoverage,
            passed: $reasons === [],
            reasons: $reasons,
        );
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', strtolower($value)) ?? '');
    }

    /**
     * @param  list<array{source_id?: string}>  $sources
     * @param  list<string>  $expectedSourceIds
     */
    private function sourceCoverage(array $sources, array $expectedSourceIds): float
    {
        if ($expectedSourceIds === []) {
            return 1.0;
        }

        $actualSourceIds = array_values(array_unique(array_filter(
            array_map(
                static fn (array $source): string => (string) ($source['source_id'] ?? ''),
                $sources,
            ),
            static fn (string $sourceId): bool => $sourceId !== '',
        )));

        $covered = array_intersect($expectedSourceIds, $actualSourceIds);

        return round(count($covered) / count($expectedSourceIds), 4);
    }
}
