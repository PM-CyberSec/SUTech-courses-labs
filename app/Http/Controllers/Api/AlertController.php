<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAlertRequest;
use App\Models\DldsEvent;
use App\Services\DldsEventQueryService;
use App\Services\EventIngestionService;
use App\Services\EventLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function __construct(
        private readonly EventLookupService $lookupService,
        private readonly DldsEventQueryService $queryService,
    ) {}

    public function store(StoreAlertRequest $request, EventIngestionService $ingestionService): JsonResponse
    {
        [$event, $isDuplicate] = $ingestionService->ingest($this->ingestionPayload($request));

        return response()->json([
            'status' => $isDuplicate ? 'duplicate' : 'stored',
            'id' => $event->id,
            'hash' => $event->event_hash,
            'event' => $event->toApiArray(),
        ], $isDuplicate ? 200 : 201);
    }

    public function index(Request $request): JsonResponse
    {
        $alertTypeId = $this->lookupService->findEventTypeIdByName('alert');
        $highSeverityIds = $this->lookupService->highSeverityIds();
        $perPage = $this->queryService->resolvePerPage($request, 25, 200);

        $query = DldsEvent::query()
            ->withLookups()
            ->where(function ($query) use ($alertTypeId, $highSeverityIds): void {
                if ($alertTypeId !== null) {
                    $query->where('event_type_id', $alertTypeId);
                    $query->orWhereNotNull('alert_type_id');
                    if ($highSeverityIds !== []) {
                        $query->orWhereIn('severity_id', $highSeverityIds);
                    }
                    $query->orWhere('description', 'like', '%alert%');

                    return;
                }

                $query->whereNotNull('alert_type_id');
                if ($highSeverityIds !== []) {
                    $query->orWhereIn('severity_id', $highSeverityIds);
                }
                $query->orWhere('description', 'like', '%alert%');
            });

        $this->queryService->applyCommonFilters($query, $request, $this->lookupService);
        $this->queryService->applySort(
            $query,
            $request,
            allowedSortKeys: ['id', 'event_time', 'severity', 'type', 'alert_type', 'process_name', 'src_ip', 'dst_ip', 'bytes_sent', 'created_at'],
            defaultSortBy: 'event_time',
            defaultSortDir: 'desc',
        );

        $alerts = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'data' => array_map(
                static fn (DldsEvent $event): array => $event->toApiArray(),
                $alerts->items(),
            ),
            'meta' => $this->queryService->paginationMeta($alerts),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function ingestionPayload(StoreAlertRequest $request): array
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
