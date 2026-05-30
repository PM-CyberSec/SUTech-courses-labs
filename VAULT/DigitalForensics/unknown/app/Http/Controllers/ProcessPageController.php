<?php

namespace App\Http\Controllers;

use App\Models\DldsEvent;
use App\Models\EventType;
use App\Models\ProcessCatalog;
use App\Models\SeverityLevel;
use App\Services\EventLookupService;
use Illuminate\Http\Request;

class ProcessPageController extends Controller
{
    public function __construct(private readonly EventLookupService $lookupService) {}

    public function index(Request $request)
    {
        $processTypeId = $this->lookupService->findEventTypeIdByName('process');
        $perPage = min(max((int) $request->query('per_page', 25), 1), 200);

        $processes = DldsEvent::query()
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
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        return view('pages.processes', [
            'processes' => $processes,
            'severityLevels' => SeverityLevel::query()->orderBy('id')->get(['id', 'name']),
            'eventTypes' => EventType::query()->orderBy('name')->get(['id', 'name']),
            'processCatalog' => ProcessCatalog::query()->orderBy('process_name')->limit(300)->get(['id', 'process_name']),
        ]);
    }
}
