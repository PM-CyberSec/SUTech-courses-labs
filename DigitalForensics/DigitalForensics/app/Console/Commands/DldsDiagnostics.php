<?php

namespace App\Console\Commands;

use App\Models\DldsEvent;
use App\Models\AlertType;
use App\Models\EventType;
use App\Models\ProcessCatalog;
use App\Models\SeverityLevel;
use Illuminate\Console\Command;

class DldsDiagnostics extends Command
{
    protected $signature = 'dlds:diagnostics';
    protected $description = 'Print DLDS database diagnostics';

    public function handle(): int
    {
        $this->info('=== DLDS Database Diagnostics ===');

        // Total events
        $total = DldsEvent::count();
        $this->line("Total events: {$total}");

        // By type
        $byType = DldsEvent::groupBy('event_type_id')
            ->selectRaw('event_type_id, count(*) as count')
            ->get()
            ->map(fn ($row) => [
                'type_id' => $row->event_type_id,
                'count' => $row->count,
                'name' => EventType::find($row->event_type_id)?->name ?? 'unknown'
            ]);
        $this->line('Events by type:');
        $this->line($byType->toJson(JSON_PRETTY_PRINT));

        // By severity
        $bySeverity = DldsEvent::leftJoin('severity_levels', 'dlds_events.severity_id', '=', 'severity_levels.id')
            ->groupBy('severity_levels.name')
            ->selectRaw('severity_levels.name, count(*) as count')
            ->get();
        $this->line('By severity:');
        $this->line($bySeverity->toJson(JSON_PRETTY_PRINT));

        // By alert_type
        $byAlert = DldsEvent::leftJoin('alert_types', 'dlds_events.alert_type_id', '=', 'alert_types.id')
            ->groupBy('alert_types.name')
            ->selectRaw('alert_types.name, count(*) as count')
            ->get();
        $this->line('By alert_type:');
        $this->line($byAlert->toJson(JSON_PRETTY_PRINT));

        // By process_name (top 10)
        $byProcess = DldsEvent::leftJoin('process_catalog', 'dlds_events.process_id', '=', 'process_catalog.id')
            ->groupBy('process_catalog.process_name')
            ->selectRaw('process_catalog.process_name, count(*) as count')
            ->orderByDesc('count')
            ->limit(10)
            ->get();
        $this->line('Top processes:');
        $this->line($byProcess->toJson(JSON_PRETTY_PRINT));

        // Null network/process
        $nullNetwork = DldsEvent::whereNull('src_ip')->whereNull('dst_ip')->count();
        $nullProcess = DldsEvent::whereNull('process_id')->where('pid', 0)->count();
        $this->line("Null network fields (no src/dst IP): {$nullNetwork}");
        $this->line("Null process fields (no pid/process_id): {$nullProcess}");

        // Latest 20 events
        $latest = DldsEvent::with(['eventType', 'severityLevel', 'process', 'alertCategory'])
            ->latest('event_time')
            ->limit(20)
            ->get();
        $latestData = $latest->map(fn($e) => [
            'id' => $e->id,
            'event_time' => $e->event_time,
            'type' => $e->type,
            'severity' => $e->severity,
            'pid' => $e->pid,
            'process_name' => $e->process_name,
            'src_ip' => $e->src_ip,
            'dst_ip' => $e->dst_ip,
            'alert_type' => $e->alert_type,
        ]);
        $this->line('Latest 20 events:');
        $this->line($latestData->toJson(JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}

