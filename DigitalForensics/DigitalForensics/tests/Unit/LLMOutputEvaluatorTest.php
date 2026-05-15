<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Evaluation\EvaluationCase;
use App\Services\Evaluation\LLMOutputEvaluator;
use PHPUnit\Framework\TestCase;

class LLMOutputEvaluatorTest extends TestCase
{
    public function test_evaluation_passes_when_answer_confidence_and_sources_match(): void
    {
        $result = (new LLMOutputEvaluator)->evaluate(new EvaluationCase(
            expectedAnswer: 'TLS exfiltration',
            actualAnswer: 'The incident is consistent with TLS exfiltration.',
            confidence: 0.88,
            sources: [['source_id' => 'src_suricata']],
            expectedSourceIds: ['src_suricata'],
        ));

        $this->assertTrue($result->passed);
        $this->assertSame(1.0, $result->sourceCoverage);
        $this->assertSame([], $result->reasons);
        $this->assertSame('pass', $result->toArray()['result']);
    }

    public function test_evaluation_fails_when_expected_answer_is_missing(): void
    {
        $result = (new LLMOutputEvaluator)->evaluate(new EvaluationCase(
            expectedAnswer: 'credential theft',
            actualAnswer: 'The incident is consistent with benign maintenance.',
            confidence: 0.90,
            sources: [['source_id' => 'src_identity']],
            expectedSourceIds: ['src_identity'],
        ));

        $this->assertFalse($result->passed);
        $this->assertContains('expected_answer_not_found', $result->reasons);
        $this->assertSame('fail', $result->toArray()['result']);
    }

    public function test_missing_source_fails_evaluation(): void
    {
        $result = (new LLMOutputEvaluator)->evaluate(new EvaluationCase(
            expectedAnswer: 'malware quarantine',
            actualAnswer: 'The response recommends malware quarantine.',
            confidence: 0.82,
            sources: [['source_id' => 'src_other']],
            expectedSourceIds: ['src_malware'],
        ));

        $this->assertFalse($result->passed);
        $this->assertSame(0.0, $result->sourceCoverage);
        $this->assertContains('source_coverage_below_threshold', $result->reasons);
    }

    public function test_low_confidence_fails_evaluation(): void
    {
        $result = (new LLMOutputEvaluator)->evaluate(new EvaluationCase(
            expectedAnswer: 'account lockout',
            actualAnswer: 'The evidence supports an account lockout response.',
            confidence: 0.20,
            sources: [['source_id' => 'src_login']],
            expectedSourceIds: ['src_login'],
            minConfidence: 0.50,
        ));

        $this->assertFalse($result->passed);
        $this->assertContains('confidence_below_threshold', $result->reasons);
    }
}
