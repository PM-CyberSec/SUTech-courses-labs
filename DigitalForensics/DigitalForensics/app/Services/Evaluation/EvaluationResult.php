<?php

declare(strict_types=1);

namespace App\Services\Evaluation;

final class EvaluationResult
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public readonly string $expectedAnswer,
        public readonly string $actualAnswer,
        public readonly float $confidence,
        public readonly float $sourceCoverage,
        public readonly bool $passed,
        public readonly array $reasons = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'expected_answer' => $this->expectedAnswer,
            'actual_answer' => $this->actualAnswer,
            'confidence' => $this->confidence,
            'source_coverage' => $this->sourceCoverage,
            'passed' => $this->passed,
            'result' => $this->passed ? 'pass' : 'fail',
            'reasons' => $this->reasons,
        ];
    }
}
