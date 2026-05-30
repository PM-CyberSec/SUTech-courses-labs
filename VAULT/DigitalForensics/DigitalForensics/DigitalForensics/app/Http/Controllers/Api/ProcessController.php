<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DldsEvent;
use App\Services\DldsEventQueryService;
use App\Services\EventLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcessController extends Controller
{
    public function __construct(
        private readonly EventLookupService $lookupService,
        private readonly DldsEventQueryService $queryService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->queryService->resolvePerPage($request, 25, 200);

        $query = DldsEvent::query()
            ->withLookups();

        $this->queryService->applyProcessScope($query, $this->lookupService);

        $this->queryService->applyCommonFilters($query, $request, $this->lookupService);
        $this->queryService->applySort(
            $query,
            $request,
            allowedSortKeys: ['id', 'event_time', 'severity', 'type', 'process_name', 'pid', 'file_path', 'created_at'],
            defaultSortBy: 'event_time',
            defaultSortDir: 'desc',
        );

        $processes = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'data' => array_map(
                static fn (DldsEvent $event): array => $event->toArray(),
                $processes->items(),
            ),
            'meta' => $this->queryService->paginationMeta($processes),
        ]);
    }
}
