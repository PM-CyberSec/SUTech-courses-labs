<?php

declare(strict_types=1);

namespace App\Services\Evaluation;

use InvalidArgumentException;

final class EvaluationCase
{
    /**
     * @param  list<array{source_id?: string, source_title?: string, excerpt?: string, confidence?: float, retrieved_at?: string}>  $sources
     * @param  list<string>  $expectedSourceIds
     */
    public function __construct(
        public readonly string $expectedAnswer,
        public readonly string $actualAnswer,
        public readonly float $confidence,
        public readonly array $sources = [],
        public readonly array $expectedSourceIds = [],
        public readonly float $minConfidence = 0.5,
        public readonly float $minSourceCoverage = 1.0,
    ) {
        if (trim($expectedAnswer) === '') {
            throw new InvalidArgumentException('Expected answer is required.');
        }

        if (trim($actualAnswer) === '') {
            throw new InvalidArgumentException('Actual answer is required.');
        }
    }
}
