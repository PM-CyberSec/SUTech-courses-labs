<?php

namespace App\Http\Controllers;

use App\Models\DldsEvent;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = DldsEvent::query()
            ->leftJoin('severity_levels', 'dlds_events.severity_id', '=', 'severity_levels.id')
            ->leftJoin('event_types', 'dlds_events.event_type_id', '=', 'event_types.id')
            ->selectRaw('COUNT(*) as total_events')
            ->selectRaw("SUM(CASE WHEN severity_levels.name = 'CRITICAL' THEN 1 ELSE 0 END) as critical_severity")
            ->selectRaw("SUM(CASE WHEN severity_levels.name = 'HIGH' THEN 1 ELSE 0 END) as high_severity")
            ->selectRaw("SUM(CASE WHEN severity_levels.name = 'MEDIUM' THEN 1 ELSE 0 END) as medium_severity")
            ->selectRaw("SUM(CASE WHEN severity_levels.name = 'LOW' THEN 1 ELSE 0 END) as low_severity")
            ->selectRaw("SUM(CASE WHEN event_types.name = 'alert' OR dlds_events.alert_type_id IS NOT NULL OR severity_levels.name IN ('HIGH', 'CRITICAL') THEN 1 ELSE 0 END) as alert_events")
            ->selectRaw("SUM(CASE WHEN event_types.name = 'network' OR dlds_events.src_ip IS NOT NULL OR dlds_events.dst_ip IS NOT NULL THEN 1 ELSE 0 END) as network_events")
            ->selectRaw("SUM(CASE WHEN event_types.name = 'process' OR dlds_events.process_id IS NOT NULL OR dlds_events.pid > 0 OR (dlds_events.file_path IS NOT NULL AND dlds_events.file_path != '') THEN 1 ELSE 0 END) as process_events")
            ->first();

        return view('pages.dashboard', [
            'totalEvents' => (int) ($stats->total_events ?? 0),
            'criticalSeverity' => (int) ($stats->critical_severity ?? 0),
            'highSeverity' => (int) ($stats->high_severity ?? 0),
            'mediumSeverity' => (int) ($stats->medium_severity ?? 0),
            'lowSeverity' => (int) ($stats->low_severity ?? 0),
            'alertEvents' => (int) ($stats->alert_events ?? 0),
            'networkEvents' => (int) ($stats->network_events ?? 0),
            'processEvents' => (int) ($stats->process_events ?? 0),
        ]);
    }
}
