<?php

namespace App\Http\Controllers;

use App\Models\AlertType;
use App\Models\DldsEvent;
use App\Models\EventType;
use App\Models\SeverityLevel;
use App\Services\EventLookupService;
use Illuminate\Http\Request;

class AlertPageController extends Controller
{
    public function __construct(private readonly EventLookupService $lookupService) {}

    public function index(Request $request)
    {
        $alertTypeId = $this->lookupService->findEventTypeIdByName('alert');
        $highSeverityIds = $this->lookupService->highSeverityIds();
        $perPage = min(max((int) $request->query('per_page', 25), 1), 200);

        $alerts = DldsEvent::query()
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
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        return view('pages.alerts', [
            'alerts' => $alerts,
            'severityLevels' => SeverityLevel::query()->orderBy('id')->get(['id', 'name']),
            'alertTypes' => AlertType::query()->orderBy('name')->get(['id', 'name']),
            'eventTypes' => EventType::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
