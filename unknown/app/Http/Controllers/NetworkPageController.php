<?php

namespace App\Http\Controllers;

use App\Models\DldsEvent;
use App\Models\EventType;
use App\Models\SeverityLevel;
use App\Services\EventLookupService;
use Illuminate\Http\Request;

class NetworkPageController extends Controller
{
    public function __construct(private readonly EventLookupService $lookupService) {}

    public function index(Request $request)
    {
        $networkTypeId = $this->lookupService->findEventTypeIdByName('network');
        $perPage = min(max((int) $request->query('per_page', 25), 1), 200);

        $network = DldsEvent::query()
            ->withLookups()
            ->where(function ($query) use ($networkTypeId): void {
                if ($networkTypeId !== null) {
                    $query->where('event_type_id', $networkTypeId);
                    $query
                        ->orWhereNotNull('src_ip')
                        ->orWhereNotNull('dst_ip');

                    return;
                }

                $query
                    ->whereNotNull('src_ip')
                    ->orWhereNotNull('dst_ip');
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        return view('pages.network', [
            'network' => $network,
            'severityLevels' => SeverityLevel::query()->orderBy('id')->get(['id', 'name']),
            'eventTypes' => EventType::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
