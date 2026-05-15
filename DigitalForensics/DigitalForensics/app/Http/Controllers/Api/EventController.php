<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDldsEventRequest;
use App\Models\DldsEvent;
use App\Services\DldsEventQueryService;
use App\Services\EventIngestionService;
use App\Services\EventLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        private readonly EventLookupService $lookupService,
        private readonly DldsEventQueryService $queryService,
    ) {}

    /**
     * Ingest event from DLDS Python engine (Zeek / Suricata / process monitor).
     */
    public function store(StoreDldsEventRequest $request, EventIngestionService $ingestionService): JsonResponse
    {
        [$event, $isDuplicate] = $ingestionService->ingest($this->ingestionPayload($request));

        return response()->json([
            'status' => $isDuplicate ? 'duplicate' : 'stored',
            'id' => $event->id,
            'hash' => $event->event_hash,
            'event' => $event->toArray(),
        ], $isDuplicate ? 200 : 201);
    }

    /**
     * Paginated event stream (for dashboard + monitoring pages).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $this->queryService->resolvePerPage($request, 25, 200);

        $query = DldsEvent::query()->withLookups();
        $this->queryService->applyCommonFilters($query, $request, $this->lookupService);
        $this->queryService->applySort($query, $request, defaultSortBy: 'event_time', defaultSortDir: 'desc');

        $results = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'data' => array_map(
                static fn (DldsEvent $event): array => $event->toArray(),
                $results->items(),
            ),
            'meta' => $this->queryService->paginationMeta($results),
        ]);
    }

    /**
     * Public read-only paginated event stream used by dashboards or embedded UIs.
     * This endpoint intentionally does not require the web auth middleware so
     * dashboards can fetch a live preview in locked-down/local environments.
     */
    public function publicIndex(Request $request): JsonResponse
    {
        $perPage = $this->queryService->resolvePerPage($request, 25, 200);

        $query = DldsEvent::query()->withLookups();
        $this->queryService->applyCommonFilters($query, $request, $this->lookupService);
        $this->queryService->applySort($query, $request, defaultSortBy: 'event_time', defaultSortDir: 'desc');

        $results = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'data' => array_map(
                static fn (DldsEvent $event): array => $event->toArray(),
                $results->items(),
            ),
            'meta' => $this->queryService->paginationMeta($results),
        ]);
    }

    /**
     * Public stats for dashboards (no auth) — used by public/embedded dashboards.
     */
    public function publicStats(): JsonResponse
    {
        return $this->stats();
    }

    public function publicShow(DldsEvent $event): JsonResponse
    {
        $event->loadMissing(['eventType', 'process', 'alertCategory', 'severityLevel']);

        return response()->json($event->toArray());
    }

    public function show(DldsEvent $event): JsonResponse
    {
        $event->loadMissing(['eventType', 'process', 'alertCategory', 'severityLevel']);

        return response()->json($event->toArray());
    }

    public function stats(): JsonResponse
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

        return response()->json([
            'total_events' => (int) ($stats->total_events ?? 0),
            'critical_severity' => (int) ($stats->critical_severity ?? 0),
            'high_severity' => (int) ($stats->high_severity ?? 0),
            'medium_severity' => (int) ($stats->medium_severity ?? 0),
            'low_severity' => (int) ($stats->low_severity ?? 0),
            'alert_events' => (int) ($stats->alert_events ?? 0),
            'network_events' => (int) ($stats->network_events ?? 0),
            'process_events' => (int) ($stats->process_events ?? 0),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function ingestionPayload(StoreDldsEventRequest $request): array
    {
        $validated = $request->validated();

        foreach ([
            'event_type',
            'type',
            'time',
            'ts',
            'process',
            'executable',
            'program',
            'path',
            'source_ip',
            'dest_ip',
            'destination_ip',
            'source_port',
            'dest_port',
            'destination_port',
            'severity_num',
            'priority',
            'alert',
            'alert_signature',
            'signature',
            'category',
            'message',
            'summary',
            'src',
            'source',
            'dst',
            'dest',
            'destination',
            'event',
        ] as $key) {
            if ($request->has($key)) {
                $validated[$key] = $request->input($key);
            }
        }

        return $validated;
    }
}
