<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DldsEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Events\NewAlertEvent;


class EventController extends Controller
{
    /**
     * Ingest event from DLDS Python engine (Zeek / Suricata / process monitor).
     */
    public function store(Request $request): JsonResponse
    {

        $request->merge([
            'src_ip' => $request->src_ip ?: null,
            'dst_ip' => $request->dst_ip ?: null,
        ]);
        
        $validated = $request->validate([
            'timestamp' => ['required', 'string', 'max:128'],
            'type' => ['required', 'in:alert,network,process,test'],

            'pid' => ['nullable', 'integer'],
            'process_name' => ['nullable', 'string', 'max:512'],
            'file' => ['nullable', 'string', 'max:8192'],

            'src_ip' => ['nullable', 'ip'],
            'dst_ip' => ['nullable', 'ip'],

            'src_port' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'dst_port' => ['nullable', 'integer', 'min:0', 'max:65535'],

            'bytes_sent' => ['nullable', 'integer', 'min:0'],

            'alert_type' => ['nullable', 'string', 'max:255'],
            'severity' => ['required', 'in:LOW,MEDIUM,HIGH,CRITICAL'],

            'description' => ['nullable', 'string', 'max:16384'],

            'event_hash' => ['nullable', 'string', 'max:64'],
        ]);

        /**
         * 🔐 Event fingerprint (deduplication key)
         * This prevents duplicate Zeek/Suricata/Python replays.
         */
        $hash = md5(
            ($validated['type'] ?? '') .
            ($validated['pid'] ?? '') .
            ($validated['src_ip'] ?? '') .
            ($validated['dst_ip'] ?? '') .
            ($validated['src_port'] ?? '') .
            ($validated['dst_port'] ?? '') .
            ($validated['bytes_sent'] ?? '') .
            ($validated['alert_type'] ?? '')
        );

        // Optional: prevent duplicates at DB level (recommended unique index)
        $existing = DldsEvent::where('event_hash', $hash)->first();
        if ($existing) {
            return response()->json([
                'status' => 'duplicate',
                'id' => $existing->id,
            ], 200);
        }

        $event = DldsEvent::create([
            'event_timestamp' => $validated['timestamp'],
            'type' => $validated['type'],

            'pid' => $validated['pid'],
            'process_name' => $validated['process_name'],
            'file' => $validated['file'],

            'src_ip' => $validated['src_ip'],
            'dst_ip' => $validated['dst_ip'],

            'src_port' => $validated['src_port'],
            'dst_port' => $validated['dst_port'],

            'bytes_sent' => $validated['bytes_sent'],

            'alert_type' => $validated['alert_type'],
            'severity' => $validated['severity'] ?? 'LOW',
            'description' => $validated['description'],

            // 🔑 critical field
            'event_hash' => $hash,
        ]);

        
    
    if ($event->type === 'alert') {
        broadcast(new NewAlertEvent($event))->toOthers();
    }
    \Log::info("DLDS EVENT HIT", $request->all());
    
    dd($request->all()); // <-- TEMP DEBUG

    return response()->json([
            'status' => 'stored',
            'id' => $event->id,
            'hash' => $hash,
        ], 201);
    }

    /**
     * Paginated event stream (for dashboard + Python correlation engine)
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 200);

        $query = DldsEvent::query()->orderByDesc('id');

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->query('severity'));
        }

        return response()->json([
            'data' => $query->paginate($perPage)->items(),
            'meta' => [
                'total' => $query->count(),
                'page' => $request->query('page', 1),
            ]
        ]);
    }
}