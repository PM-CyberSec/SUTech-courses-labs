<?php

namespace App\Http\Controllers;

use App\Models\DldsEvent;
use App\Models\EventType;
use App\Models\SeverityLevel;
use App\Services\DldsEventQueryService;
use App\Services\EventLookupService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly EventLookupService $lookupService,
        private readonly DldsEventQueryService $queryService,
    ) {}

    public function index(Request $request)
    {
        // Get event type IDs for explicit type-based counting
        $alertTypeId = $this->lookupService->findEventTypeIdByName('alert');
        $networkTypeId = $this->lookupService->findEventTypeIdByName('network');
        $processTypeId = $this->lookupService->findEventTypeIdByName('process');

        // Build CASE statements for each type
        $alertCase = $alertTypeId 
            ? "SUM(CASE WHEN event_type_id = {$alertTypeId} OR alert_type_id IS NOT NULL OR (event_type_id IS NULL AND severity_levels.name IN ('HIGH', 'CRITICAL')) THEN 1 ELSE 0 END)"
            : "SUM(CASE WHEN alert_type_id IS NOT NULL OR severity_levels.name IN ('HIGH', 'CRITICAL') THEN 1 ELSE 0 END)";
        
        $networkCase = $networkTypeId
            ? "SUM(CASE WHEN event_type_id = {$networkTypeId} OR (event_type_id IS NULL AND (src_ip IS NOT NULL OR dst_ip IS NOT NULL)) THEN 1 ELSE 0 END)"
            : "SUM(CASE WHEN src_ip IS NOT NULL OR dst_ip IS NOT NULL THEN 1 ELSE 0 END)";
        
        $processCase = $processTypeId
            ? "SUM(CASE WHEN event_type_id = {$processTypeId} OR (event_type_id IS NULL AND (process_id IS NOT NULL OR pid > 0 OR (file_path IS NOT NULL AND file_path != ''))) THEN 1 ELSE 0 END)"
            : "SUM(CASE WHEN process_id IS NOT NULL OR pid > 0 OR (file_path IS NOT NULL AND file_path != '') THEN 1 ELSE 0 END)";

        $stats = DldsEvent::query()
            ->leftJoin('severity_levels', 'dlds_events.severity_id', '=', 'severity_levels.id')
            ->selectRaw('COUNT(*) as total_events')
            ->selectRaw("SUM(CASE WHEN severity_levels.name = 'CRITICAL' THEN 1 ELSE 0 END) as critical_severity")
            ->selectRaw("SUM(CASE WHEN severity_levels.name = 'HIGH' THEN 1 ELSE 0 END) as high_severity")
            ->selectRaw("SUM(CASE WHEN severity_levels.name = 'MEDIUM' THEN 1 ELSE 0 END) as medium_severity")
            ->selectRaw("SUM(CASE WHEN severity_levels.name = 'LOW' THEN 1 ELSE 0 END) as low_severity")
            ->selectRaw("{$alertCase} as alert_events")
            ->selectRaw("{$networkCase} as network_events")
            ->selectRaw("{$processCase} as process_events")
            ->first();

        $dashboardEventsQuery = DldsEvent::query()->withLookups();
        $this->queryService->applyCommonFilters($dashboardEventsQuery, $request, $this->lookupService);
        $this->queryService->applySort($dashboardEventsQuery, $request, defaultSortBy: 'event_time', defaultSortDir: 'desc');

        $dashboardEvents = $dashboardEventsQuery->paginate(25)->appends($request->query());

        return view('pages.dashboard', [
            'totalEvents' => (int) ($stats->total_events ?? 0),
            'criticalSeverity' => (int) ($stats->critical_severity ?? 0),
            'highSeverity' => (int) ($stats->high_severity ?? 0),
            'mediumSeverity' => (int) ($stats->medium_severity ?? 0),
            'lowSeverity' => (int) ($stats->low_severity ?? 0),
            'alertEvents' => (int) ($stats->alert_events ?? 0),
            'networkEvents' => (int) ($stats->network_events ?? 0),
            'processEvents' => (int) ($stats->process_events ?? 0),
            'dashboardEvents' => $dashboardEvents,
            'eventTypes' => EventType::query()->orderBy('name')->get(['id', 'name']),
            'severityLevels' => SeverityLevel::query()->orderBy('id')->get(['id', 'name']),
        ]);
    }
}
