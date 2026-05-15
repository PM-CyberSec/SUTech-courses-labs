<?php

declare(strict_types=1);

namespace App\Services\Evaluation;

final class SampleEvaluations
{
    /**
     * @return list<EvaluationCase>
     */
    public static function cases(): array
    {
        return [
            new EvaluationCase(
                expectedAnswer: 'TLS exfiltration',
                actualAnswer: 'The alert indicates TLS exfiltration with unusual outbound bytes.',
                confidence: 0.91,
                sources: [[
                    'source_id' => 'src_suricata',
                    'source_title' => 'Suricata Triage',
                    'excerpt' => 'Suricata detected exfiltration over TLS.',
                    'confidence' => 0.95,
                    'retrieved_at' => '2026-05-04T00:00:00.000000Z',
                ]],
                expectedSourceIds: ['src_suricata'],
            ),
            new EvaluationCase(
                expectedAnswer: 'account lockout',
                actualAnswer: 'The evidence supports an account lockout runbook response.',
                confidence: 0.34,
                sources: [[
                    'source_id' => 'src_login',
                    'source_title' => 'Login Runbook',
                    'excerpt' => 'Failed login lockouts require identity provider review.',
                    'confidence' => 0.80,
                    'retrieved_at' => '2026-05-04T00:00:00.000000Z',
                ]],
                expectedSourceIds: ['src_login'],
            ),
            new EvaluationCase(
                expectedAnswer: 'malware quarantine',
                actualAnswer: 'The answer recommends malware quarantine.',
                confidence: 0.82,
                sources: [],
                expectedSourceIds: ['src_malware'],
            ),
        ];
    }
}
