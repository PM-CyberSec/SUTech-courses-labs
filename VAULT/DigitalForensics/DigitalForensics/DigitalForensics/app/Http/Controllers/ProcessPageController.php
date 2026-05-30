<?php

namespace App\Http\Controllers;

use App\Models\DldsEvent;
use App\Models\EventType;
use App\Models\ProcessCatalog;
use App\Models\SeverityLevel;
use App\Services\DldsEventQueryService;
use App\Services\EventLookupService;
use Illuminate\Http\Request;

class ProcessPageController extends Controller
{
    public function __construct(
        private readonly EventLookupService $lookupService,
        private readonly DldsEventQueryService $queryService,
    ) {}

    public function index(Request $request)
    {
        $perPage = $this->queryService->resolvePerPage($request, 25, 200);

        $processesQuery = DldsEvent::query()
            ->withLookups();

        $this->queryService->applyProcessScope($processesQuery, $this->lookupService);

        $this->queryService->applyCommonFilters($processesQuery, $request, $this->lookupService);
        $this->queryService->applySort($processesQuery, $request, defaultSortBy: 'event_time', defaultSortDir: 'desc');

        $processes = $processesQuery->paginate($perPage)->appends($request->query());

        return view('pages.processes', [
            'processes' => $processes,
            'severityLevels' => SeverityLevel::query()->orderBy('id')->get(['id', 'name']),
            'eventTypes' => EventType::query()->orderBy('name')->get(['id', 'name']),
            'processCatalog' => ProcessCatalog::query()->orderBy('process_name')->limit(300)->get(['id', 'process_name']),
        ]);
    }
}
