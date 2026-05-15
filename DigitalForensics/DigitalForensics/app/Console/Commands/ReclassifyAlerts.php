<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\EventUpdated;
use App\Models\DldsEvent;
use App\Services\AI\EventClassificationGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReclassifyAlerts extends Command
{
    protected $signature = 'dlds:reclassify-alerts {--dry-run : Preview classification changes without writing to the database}';

    protected $description = 'Reclassify existing HIGH/CRITICAL alert events that were incorrectly labeled benign.';

    public function __construct(private readonly EventClassificationGuard $classificationGuard)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $candidates = 0;
        $changed = 0;

        DldsEvent::query()
            ->withLookups()
            ->whereHas('eventType', static fn ($query) => $query->where('name', 'alert'))
            ->whereHas('severityLevel', static fn ($query) => $query->whereIn('name', ['HIGH', 'CRITICAL']))
            ->where('ai_label', 'benign')
            ->orderBy('id')
            ->chunkById(200, function ($events) use (&$candidates, &$changed, $dryRun): void {
                foreach ($events as $event) {
                    ++$candidates;

                    $guarded = $this->classificationGuard->apply($event->toArray());
                    $newLabel = (string) ($guarded['ai_label'] ?? 'benign');
                    $newConfidence = (float) ($guarded['confidence'] ?? 0.0);
                    $newAnomaly = (float) ($guarded['anomaly_score'] ?? 0.0);
                    $newReason = (string) ($guarded['ai_reason'] ?? '');

                    $hasChanged = $newLabel !== (string) $event->ai_label
                        || abs($newConfidence - (float) $event->confidence) > 0.0001
                        || abs($newAnomaly - (float) $event->anomaly_score) > 0.0001
                        || $newReason !== (string) $event->ai_reason;

                    if (! $hasChanged) {
                        continue;
                    }

                    ++$changed;

                    if (! $dryRun) {
                        $event->forceFill([
                            'ai_label' => $newLabel,
                            'confidence' => $newConfidence,
                            'anomaly_score' => $newAnomaly,
                            'ai_reason' => $newReason,
                        ])->save();

                        broadcast(new EventUpdated($event));
                    }
                }
            });

        Log::info('DLDS alert reclassification completed', [
            'event_type' => 'dlds.reclassify_alerts.completed',
            'dry_run' => $dryRun,
            'candidates' => $candidates,
            'changed' => $changed,
        ]);

        $mode = $dryRun ? 'Dry run' : 'Reclassification';
        $this->info("{$mode} complete. Candidates: {$candidates}. Updated: {$changed}.");

        return self::SUCCESS;
    }
}
