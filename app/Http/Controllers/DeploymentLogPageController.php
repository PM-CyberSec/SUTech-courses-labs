<?php

namespace App\Http\Controllers;

use App\Models\DeploymentLog;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeploymentLogPageController extends Controller
{
    public function index(Request $request): View
    {
        $logs = DeploymentLog::query()
            ->with(['deployment.device:id,hostname,mgmt_ip'])
            ->when($request->filled('device_id'), function ($query) use ($request): void {
                $query->whereHas('deployment', function ($builder) use ($request): void {
                    $builder->where('device_id', $request->integer('device_id'));
                });
            })
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->whereHas('deployment', function ($builder) use ($request): void {
                    $builder->where('status', (string) $request->string('status'));
                });
            })
            ->when($request->filled('level'), fn ($query) => $query->where('level', (string) $request->string('level')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', (string) $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', (string) $request->string('date_to')))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('logs.index', [
            'logs' => $logs,
            'devices' => Device::query()->orderBy('hostname')->get(['id', 'hostname']),
            'statuses' => ['pending', 'running', 'success', 'failed', 'rolled_back'],
            'levels' => ['info', 'warning', 'error'],
            'currentRole' => $request->attributes->get('resolved_role', session('role', 'viewer')),
        ]);
    }
}
