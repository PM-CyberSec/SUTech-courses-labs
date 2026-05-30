<?php

namespace App\Http\Controllers;

use App\Models\AlertType;
use App\Models\DldsEvent;
use App\Models\EventType;
use App\Models\SeverityLevel;
use App\Services\DldsEventQueryService;
use App\Services\EventLookupService;
use Illuminate\Http\Request;

class AlertPageController extends Controller
{
    public function __construct(
        private readonly EventLookupService $lookupService,
        private readonly DldsEventQueryService $queryService,
    ) {}

    public function index(Request $request)
    {
        $perPage = $this->queryService->resolvePerPage($request, 25, 200);

        $alertsQuery = DldsEvent::query()
            ->withLookups();

        $this->queryService->applyAlertScope($alertsQuery, $this->lookupService);

        $this->queryService->applyCommonFilters($alertsQuery, $request, $this->lookupService);
        $this->queryService->applySort($alertsQuery, $request, defaultSortBy: 'event_time', defaultSortDir: 'desc');

        $alerts = $alertsQuery->paginate($perPage)->appends($request->query());

        return view('pages.alerts', [
            'alerts' => $alerts,
            'severityLevels' => SeverityLevel::query()->orderBy('id')->get(['id', 'name']),
            'alertTypes' => AlertType::query()->orderBy('name')->get(['id', 'name']),
            'eventTypes' => EventType::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
