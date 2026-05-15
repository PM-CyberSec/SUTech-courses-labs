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
        $processTypeId = $this->lookupService->findEventTypeIdByName('process');
        $perPage = $this->queryService->resolvePerPage($request, 25, 200);

        $query = DldsEvent::query()
            ->withLookups()
            ->where(function ($query) use ($processTypeId): void {
                if ($processTypeId !== null) {
                    $query->where('event_type_id', $processTypeId);
                    $query
                        ->orWhere('pid', '>', 0)
                        ->orWhereNotNull('process_id')
                        ->orWhere(function ($inner): void {
                            $inner->whereNotNull('file_path')
                                ->where('file_path', '!=', '');
                        });

                    return;
                }

                $query
                    ->where('pid', '>', 0)
                    ->orWhereNotNull('process_id')
                    ->orWhere(function ($inner): void {
                        $inner->whereNotNull('file_path')
                            ->where('file_path', '!=', '');
                    });
            });

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
                static fn (DldsEvent $event): array => $event->toApiArray(),
                $processes->items(),
            ),
            'meta' => $this->queryService->paginationMeta($processes),
        ]);
    }
}
