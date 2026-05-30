<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DldsEvent;
use App\Services\DldsEventQueryService;
use App\Services\EventLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NetworkController extends Controller
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

        $this->queryService->applyNetworkScope($query, $this->lookupService);

        $this->queryService->applyCommonFilters($query, $request, $this->lookupService);
        $this->queryService->applySort(
            $query,
            $request,
            allowedSortKeys: ['id', 'event_time', 'severity', 'type', 'src_ip', 'dst_ip', 'bytes_sent', 'src_port', 'dst_port', 'created_at'],
            defaultSortBy: 'event_time',
            defaultSortDir: 'desc',
        );

        $network = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'data' => array_map(
                static fn (DldsEvent $event): array => $event->toArray(),
                $network->items(),
            ),
            'meta' => $this->queryService->paginationMeta($network),
        ]);
    }
}
