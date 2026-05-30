<?php

namespace App\Http\Controllers;

use App\Models\DldsEvent;
use App\Models\EventType;
use App\Models\SeverityLevel;
use App\Services\DldsEventQueryService;
use App\Services\EventLookupService;
use Illuminate\Http\Request;

class NetworkPageController extends Controller
{
    public function __construct(
        private readonly EventLookupService $lookupService,
        private readonly DldsEventQueryService $queryService,
    ) {}

    public function index(Request $request)
    {
        $perPage = $this->queryService->resolvePerPage($request, 25, 200);

        $networkQuery = DldsEvent::query()
            ->withLookups();

        $this->queryService->applyNetworkScope($networkQuery, $this->lookupService);

        $this->queryService->applyCommonFilters($networkQuery, $request, $this->lookupService);
        $this->queryService->applySort($networkQuery, $request, defaultSortBy: 'event_time', defaultSortDir: 'desc');

        $network = $networkQuery->paginate($perPage)->appends($request->query());

        return view('pages.network', [
            'network' => $network,
            'severityLevels' => SeverityLevel::query()->orderBy('id')->get(['id', 'name']),
            'eventTypes' => EventType::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
