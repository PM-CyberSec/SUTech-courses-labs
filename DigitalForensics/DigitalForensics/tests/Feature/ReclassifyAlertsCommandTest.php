<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DldsEvent;
use App\Services\EventLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReclassifyAlertsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reclassify_alerts_dry_run_does_not_persist_changes(): void
    {
        $event = $this->seedHighAlertLabeledBenign();

        $this->artisan('dlds:reclassify-alerts', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run complete.')
            ->assertSuccessful();

        $event->refresh();
        $this->assertSame('benign', $event->ai_label);
    }

    public function test_reclassify_alerts_updates_existing_bad_records(): void
    {
        $event = $this->seedHighAlertLabeledBenign();

        $this->artisan('dlds:reclassify-alerts')
            ->expectsOutputToContain('Reclassification complete.')
            ->assertSuccessful();

        $event->refresh();
        $this->assertNotSame('benign', $event->ai_label);
        $this->assertGreaterThanOrEqual(0.80, (float) $event->confidence);
        $this->assertGreaterThanOrEqual(0.80, (float) $event->anomaly_score);
    }

    private function seedHighAlertLabeledBenign(): DldsEvent
    {
        /** @var EventLookupService $lookup */
        $lookup = app(EventLookupService::class);

        return DldsEvent::query()->create([
            'event_time' => now()->subMinute(),
            'event_type_id' => $lookup->resolveEventTypeId('alert'),
            'severity_id' => $lookup->resolveSeverityId('HIGH'),
            'alert_type_id' => $lookup->resolveAlertTypeId('GPL ATTACK_RESPONSE id check returned root'),
            'description' => 'GPL ATTACK_RESPONSE id check returned root',
            'event_hash' => hash('sha256', 'reclassify-test'),
            'ai_label' => 'benign',
            'confidence' => 0.96,
            'anomaly_score' => 0.96,
            'ai_reason' => 'Model predicted benign behavior.',
        ]);
    }
}
