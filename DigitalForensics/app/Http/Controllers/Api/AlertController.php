<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DldsEvent;

class AlertController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->all();

        DldsEvent::create([
            'timestamp' => $data['timestamp'] ?? now(),
            'type' => $data['type'] ?? 'alert',
            'pid' => $data['pid'] ?? 0,
            'process_name' => $data['process_name'] ?? '',
            'file' => $data['file'] ?? '',
            'src_ip' => $data['src_ip'] ?? '',
            'src_port' => $data['src_port'] ?? 0,
            'dst_ip' => $data['dst_ip'] ?? '',
            'dst_port' => $data['dst_port'] ?? 0,
            'bytes_sent' => $data['bytes_sent'] ?? 0,
            'alert_type' => $data['alert_type'] ?? '',
            'severity' => $data['severity'] ?? 'LOW',
            'description' => $data['description'] ?? '',
        ]);

        return response()->json([
            "status" => "ok"
        ]);
    }

    public function index()
    {
        return response()->json(
            DldsEvent::orderBy('id', 'desc')->limit(100)->get()
        );
    }
}