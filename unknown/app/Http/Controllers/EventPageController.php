<?php

namespace App\Http\Controllers;

use App\Models\AlertType;
use App\Models\DldsEvent;
use App\Models\EventType;
use App\Models\ProcessCatalog;
use App\Models\SeverityLevel;
use Illuminate\Http\Request;

class EventPageController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 25), 1), 200);

        $events = DldsEvent::query()
            ->withLookups()
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        return view('pages.events', [
            'events' => $events,
            'severityLevels' => SeverityLevel::query()->orderBy('id')->get(['id', 'name']),
            'eventTypes' => EventType::query()->orderBy('name')->get(['id', 'name']),
            'alertTypes' => AlertType::query()->orderBy('name')->get(['id', 'name']),
            'processCatalog' => ProcessCatalog::query()->orderBy('process_name')->limit(300)->get(['id', 'process_name']),
        ]);
    }
}
