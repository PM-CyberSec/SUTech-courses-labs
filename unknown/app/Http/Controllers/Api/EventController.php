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
            'event' => $event->toApiArray(),
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
                static fn (DldsEvent $event): array => $event->toApiArray(),
                $results->items(),
            ),
            'meta' => $this->queryService->paginationMeta($results),
        ]);
    }

    public function stats(): JsonResponse
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
            'dst',
            'event',
        ] as $key) {
            if ($request->has($key)) {
                $validated[$key] = $request->input($key);
            }
        }

        return $validated;
    }
}
