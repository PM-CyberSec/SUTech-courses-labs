<?php

use App\Models\DldsEvent;
use App\Models\SeverityLevel;
use App\Services\Evaluation\LLMOutputEvaluator;
use App\Services\Evaluation\SampleEvaluations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| DLDS AUTOMATION ENGINE (SIEM BACKEND)
|--------------------------------------------------------------------------
| Runs correlation, cleanup, anomaly detection
*/

Artisan::command('dlds:correlate', function (): void {
    $windowMinutes = (int) env('DLDS_CORRELATION_WINDOW_MINUTES', 5);

    $summary = DldsEvent::query()
        ->where('dlds_events.created_at', '>=', now()->subMinutes($windowMinutes))
        ->leftJoin('event_types', 'dlds_events.event_type_id', '=', 'event_types.id')
        ->selectRaw("COALESCE(event_types.name, 'unknown') as event_type")
        ->selectRaw('COUNT(*) as total')
        ->groupBy('event_type')
        ->pluck('total', 'event_type')
        ->toArray();

    Log::info('DLDS correlation summary', [
        'window_minutes' => $windowMinutes,
        'summary' => $summary,
    ]);

    $this->info('Correlation summary generated.');
})->purpose('Build an event correlation summary for the latest ingestion window.');

Artisan::command('dlds:cleanup', function (): void {
    $retentionDays = (int) env('DLDS_RETENTION_DAYS', 30);

    $highSeverityIds = SeverityLevel::query()
        ->whereIn('name', ['HIGH', 'CRITICAL'])
        ->pluck('id')
        ->all();

    $deleted = DldsEvent::query()
        ->where('created_at', '<', now()->subDays($retentionDays))
        ->where(function ($query) use ($highSeverityIds): void {
            $query
                ->whereNull('alert_type_id')
                ->when($highSeverityIds !== [], fn ($inner) => $inner->whereNotIn('severity_id', $highSeverityIds));
        })
        ->delete();

    Log::info('DLDS cleanup completed', [
        'retention_days' => $retentionDays,
        'deleted_records' => $deleted,
    ]);

    $this->info("Cleanup done. Deleted {$deleted} old low-priority records.");
})->purpose('Purge stale low-priority telemetry records.');

Artisan::command('dlds:score-events', function (): void {
    $lowSeverityId = SeverityLevel::query()->where('name', 'LOW')->value('id');

    if ($lowSeverityId === null) {
        $lowSeverityId = SeverityLevel::query()->create(['name' => 'LOW'])->id;
    }

    $updated = DldsEvent::query()
        ->whereNull('severity_id')
        ->update(['severity_id' => $lowSeverityId]);

    Log::info('DLDS score-events completed', [
        'updated_records' => $updated,
    ]);

    $this->info("Severity normalization done. Updated {$updated} records.");
})->purpose('Normalize missing event severities.');

Artisan::command('dlds:health-check', function (): void {
    $recentEvents = DldsEvent::query()
        ->where('created_at', '>=', now()->subMinutes(10))
        ->count();

    $this->info("DLDS health check passed. Recent events (10m): {$recentEvents}");
})->purpose('Run basic DLDS backend health checks.');

Schedule::command('dlds:correlate')->everyMinute();
Schedule::command('dlds:cleanup')->daily();
Schedule::command('dlds:score-events')->everyFiveMinutes();
Schedule::command('dlds:health-check')->everyTenMinutes();

Artisan::command('security:assert-production-config', function (): int {
    $unsafe = [];

    $appEnv = (string) config('app.env');
    $appDebug = (bool) config('app.debug');
    $appUrl = (string) config('app.url');
    $sessionSecure = (bool) config('session.secure');

    if ($appEnv !== 'production') {
        $unsafe[] = 'APP_ENV must be production';
    }

    if ($appDebug) {
        $unsafe[] = 'APP_DEBUG must be false';
    }

    if (! str_starts_with($appUrl, 'https://')) {
        $unsafe[] = 'APP_URL must use https://';
    }

    if (! $sessionSecure) {
        $unsafe[] = 'SESSION_SECURE_COOKIE must be true';
    }

    if ($unsafe !== []) {
        foreach ($unsafe as $issue) {
            $this->error($issue);
        }

        return self::FAILURE;
    }

    $this->info('Production security configuration checks passed.');

    return self::SUCCESS;
})->purpose('Fail CI/startup when production security settings are unsafe.');

Artisan::command('ai:evaluate', function (): int {
    /** @var LLMOutputEvaluator $evaluator */
    $evaluator = app(LLMOutputEvaluator::class);
    $results = array_map(
        static fn ($case) => $evaluator->evaluate($case),
        SampleEvaluations::cases(),
    );

    $this->table(
        ['#', 'Result', 'Confidence', 'Source Coverage', 'Reasons'],
        array_map(
            static fn ($result, int $index): array => [
                $index + 1,
                $result->passed ? 'PASS' : 'FAIL',
                number_format($result->confidence, 2),
                number_format($result->sourceCoverage, 2),
                $result->reasons === [] ? '-' : implode(', ', $result->reasons),
            ],
            $results,
            array_keys($results),
        ),
    );

    $passed = count(array_filter($results, static fn ($result): bool => $result->passed));
    $failed = count($results) - $passed;

    $this->info("AI evaluation summary: {$passed} passed, {$failed} failed.");

    return self::SUCCESS;
})->purpose('Run deterministic sample evaluations for RAG/LLM outputs.');
