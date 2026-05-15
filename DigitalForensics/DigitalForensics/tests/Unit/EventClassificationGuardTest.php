<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AI\EventClassificationGuard;
use Tests\TestCase;

class EventClassificationGuardTest extends TestCase
{
    public function test_high_alert_with_gpl_attack_response_must_not_be_benign(): void
    {
        $guard = app(EventClassificationGuard::class);

        $result = $guard->apply([
            'type' => 'alert',
            'severity' => 'HIGH',
            'alert_type' => 'GPL ATTACK_RESPONSE id check returned root',
            'description' => 'GPL ATTACK_RESPONSE id check returned root',
            'ai_label' => 'benign',
            'confidence' => 0.96,
            'anomaly_score' => 0.96,
            'ai_reason' => 'Model predicted benign behavior.',
        ]);

        $this->assertNotSame('benign', $result['ai_label']);
        $this->assertGreaterThanOrEqual(0.80, $result['confidence']);
        $this->assertGreaterThanOrEqual(0.80, $result['anomaly_score']);
        $this->assertStringContainsString('override', strtolower((string) $result['ai_reason']));
    }

    public function test_critical_alert_is_reclassified_as_malicious(): void
    {
        $guard = app(EventClassificationGuard::class);

        $result = $guard->apply([
            'type' => 'alert',
            'severity' => 'CRITICAL',
            'alert_type' => 'ET POLICY suspicious critical alert',
            'description' => 'Critical IDS finding',
            'ai_label' => 'benign',
            'confidence' => 0.92,
            'anomaly_score' => 0.22,
            'ai_reason' => 'Model predicted benign behavior.',
        ]);

        $this->assertSame('malicious', $result['ai_label']);
        $this->assertGreaterThanOrEqual(0.95, $result['anomaly_score']);
    }

    public function test_low_zeek_network_event_can_remain_benign_with_low_anomaly_score(): void
    {
        $guard = app(EventClassificationGuard::class);

        $result = $guard->apply([
            'type' => 'network',
            'severity' => 'LOW',
            'alert_type' => null,
            'description' => 'Zeek conn log',
            'ai_label' => 'benign',
            'confidence' => 0.91,
            'anomaly_score' => 0.91,
            'ai_reason' => 'Model predicted benign behavior.',
        ]);

        $this->assertSame('benign', $result['ai_label']);
        $this->assertLessThanOrEqual(0.30, $result['anomaly_score']);
    }

    public function test_anomaly_score_aligns_with_label_semantics(): void
    {
        $guard = app(EventClassificationGuard::class);

        $benign = $guard->apply([
            'type' => 'network',
            'severity' => 'LOW',
            'description' => 'Normal flow',
            'ai_label' => 'benign',
            'confidence' => 0.88,
            'anomaly_score' => 0.88,
        ]);

        $suspicious = $guard->apply([
            'type' => 'alert',
            'severity' => 'MEDIUM',
            'alert_type' => 'Suspicious behavior',
            'description' => 'Suspicious command observed',
            'ai_label' => 'suspicious',
            'confidence' => 0.61,
            'anomaly_score' => 0.12,
        ]);

        $this->assertLessThanOrEqual(0.30, $benign['anomaly_score']);
        $this->assertGreaterThanOrEqual(0.60, $suspicious['anomaly_score']);
    }

    public function test_override_reason_preserves_explanation_context(): void
    {
        $guard = app(EventClassificationGuard::class);

        $result = $guard->apply([
            'type' => 'alert',
            'severity' => 'HIGH',
            'alert_type' => 'ET MALWARE Possible Exfiltration',
            'description' => 'Potential malware exfiltration attempt',
            'ai_label' => 'benign',
            'confidence' => 0.93,
            'anomaly_score' => 0.93,
            'ai_reason' => 'Model predicted benign behavior.',
        ]);

        $this->assertStringContainsString('HIGH alert matched attack keyword', (string) $result['ai_reason']);
        $this->assertStringContainsString('Previous classification was', (string) $result['ai_reason']);
    }
}
