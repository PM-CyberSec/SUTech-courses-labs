<?php

declare(strict_types=1);

namespace App\Services\AI;

class EventClassificationGuard
{
    private const KEYWORDS = [
        'ATTACK',
        'EXPLOIT',
        'TROJAN',
        'MALWARE',
        'SHELLCODE',
        'SCAN',
        'COMMAND',
        'ROOT',
        'C2',
        'EXFILTRATION',
        'SUSPICIOUS',
    ];

    private const MALICIOUS_KEYWORDS = [
        'ATTACK',
        'EXPLOIT',
        'TROJAN',
        'MALWARE',
        'SHELLCODE',
        'ROOT',
        'C2',
        'EXFILTRATION',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function apply(array $attributes): array
    {
        $type = strtolower(trim((string) ($attributes['type'] ?? '')));
        $severity = strtoupper(trim((string) ($attributes['severity'] ?? 'LOW')));
        $alertType = trim((string) ($attributes['alert_type'] ?? ''));
        $description = trim((string) ($attributes['description'] ?? ''));
        $currentLabel = strtolower(trim((string) ($attributes['ai_label'] ?? '')));
        $currentLabel = in_array($currentLabel, ['benign', 'suspicious', 'malicious'], true)
            ? $currentLabel
            : 'benign';
        $currentConfidence = $this->clamp((float) ($attributes['confidence'] ?? 0.0));
        $currentAnomaly = $this->clamp((float) ($attributes['anomaly_score'] ?? $currentConfidence));
        $currentReason = trim((string) ($attributes['ai_reason'] ?? ''));
        $keyword = $this->firstKeywordMatch($alertType, $description);

        $override = $this->overrideFor(
            type: $type,
            severity: $severity,
            alertType: $alertType,
            keyword: $keyword,
            currentLabel: $currentLabel,
        );

        if ($override !== null) {
            $attributes['ai_label'] = $override['label'];
            $attributes['confidence'] = $override['confidence'];
            $attributes['anomaly_score'] = $override['anomaly_score'];
            $attributes['ai_reason'] = $override['reason'];

            return $attributes;
        }

        $attributes['ai_label'] = $currentLabel;
        $attributes['confidence'] = round($currentConfidence, 4);
        $attributes['anomaly_score'] = $this->alignedAnomalyScore($currentLabel, $currentAnomaly);
        $attributes['ai_reason'] = $currentReason;

        return $attributes;
    }

    private function firstKeywordMatch(string $alertType, string $description): ?string
    {
        $haystack = strtoupper($alertType.' '.$description);

        foreach (self::KEYWORDS as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return $keyword;
            }
        }

        return null;
    }

    /**
     * @return array{label: string, confidence: float, anomaly_score: float, reason: string}|null
     */
    private function overrideFor(
        string $type,
        string $severity,
        string $alertType,
        ?string $keyword,
        string $currentLabel,
    ): ?array {
        if ($type !== 'alert') {
            return null;
        }

        if ($severity === 'CRITICAL') {
            return [
                'label' => 'malicious',
                'confidence' => 0.98,
                'anomaly_score' => 0.99,
                'reason' => $this->overrideReason(
                    'Severity-aware safety override: CRITICAL security alerts cannot be classified as benign.',
                    $currentLabel,
                ),
            ];
        }

        if ($severity === 'HIGH' && $keyword !== null) {
            $label = in_array($keyword, self::MALICIOUS_KEYWORDS, true) ? 'malicious' : 'suspicious';

            return [
                'label' => $label,
                'confidence' => $label === 'malicious' ? 0.93 : 0.86,
                'anomaly_score' => $label === 'malicious' ? 0.97 : 0.82,
                'reason' => $this->overrideReason(
                    "Severity-aware safety override: HIGH alert matched attack keyword '{$keyword}'"
                    .($alertType !== '' ? " in '{$alertType}'." : '.'),
                    $currentLabel,
                ),
            ];
        }

        if ($severity === 'HIGH' && $alertType !== '') {
            return [
                'label' => 'suspicious',
                'confidence' => 0.84,
                'anomaly_score' => 0.80,
                'reason' => $this->overrideReason(
                    'Severity-aware safety override: HIGH alerts with IDS signatures cannot be classified as benign.',
                    $currentLabel,
                ),
            ];
        }

        if ($severity === 'MEDIUM' && ($alertType !== '' || $keyword !== null) && $currentLabel === 'benign') {
            return [
                'label' => 'suspicious',
                'confidence' => 0.72,
                'anomaly_score' => 0.65,
                'reason' => $this->overrideReason(
                    'Severity-aware safety override: MEDIUM alerts with IDS context require at least a suspicious label.',
                    $currentLabel,
                ),
            ];
        }

        return null;
    }

    private function alignedAnomalyScore(string $label, float $score): float
    {
        $normalized = $this->clamp($score);

        return match ($label) {
            'malicious' => round(max($normalized, 0.85), 4),
            'suspicious' => round(max($normalized, 0.60), 4),
            default => round(min($normalized, 0.30), 4),
        };
    }

    private function overrideReason(string $baseReason, string $currentLabel): string
    {
        return $currentLabel === ''
            ? $baseReason
            : "{$baseReason} Previous classification was '{$currentLabel}'.";
    }

    private function clamp(float $value): float
    {
        return min(max($value, 0.0), 1.0);
    }
}
