<?php

namespace App\Http\Controllers;

use App\Models\AlertType;
use App\Models\DldsEvent;
use App\Models\EventType;
use App\Models\ProcessCatalog;
use App\Models\SeverityLevel;
use App\Services\DldsEventQueryService;
use App\Services\EventLookupService;
use Illuminate\Http\Request;

class EventPageController extends Controller
{
    public function __construct(
        private readonly EventLookupService $lookupService,
        private readonly DldsEventQueryService $queryService,
    ) {}

    public function index(Request $request)
    {
        $perPage = $this->queryService->resolvePerPage($request, 25, 200);

        $eventsQuery = DldsEvent::query()->withLookups();
        $this->queryService->applyCommonFilters($eventsQuery, $request, $this->lookupService);
        $this->queryService->applySort($eventsQuery, $request, defaultSortBy: 'event_time', defaultSortDir: 'desc');

        $events = $eventsQuery->paginate($perPage)->appends($request->query());

        return view('pages.events', [
            'events' => $events,
            'severityLevels' => SeverityLevel::query()->orderBy('id')->get(['id', 'name']),
            'eventTypes' => EventType::query()->orderBy('name')->get(['id', 'name']),
            'alertTypes' => AlertType::query()->orderBy('name')->get(['id', 'name']),
            'processCatalog' => ProcessCatalog::query()->orderBy('process_name')->limit(300)->get(['id', 'process_name']),
        ]);
    }

    public function show(DldsEvent $event)
    {
        $event->loadMissing(['eventType', 'process', 'alertCategory', 'severityLevel']);
        return view('pages.event-details', [
            'event' => $event,
        ]);
    }
}
