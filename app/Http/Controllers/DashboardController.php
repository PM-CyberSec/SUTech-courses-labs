<?php

namespace App\Http\Controllers;

use App\Models\ConfigTemplate;
use App\Models\Deployment;
use App\Models\DeploymentLog;
use App\Models\Device;
use App\Models\Inventory;
use App\Services\Analytics\MetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(private readonly MetricsService $metricsService) {}

    public function index(Request $request)
    {
        $stats = array_merge([
            'devices' => Device::count(),
            'inventories' => Inventory::count(),
            'templates' => ConfigTemplate::count(),
        ], $this->metricsService->summarizeDeployments());

        $latestDeployments = Deployment::query()
            ->with(['device:id,hostname,mgmt_ip', 'configTemplate:id,name'])
            ->latest('id')
            ->limit(10)
            ->get();

        $deviceStatus = Device::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $recentLogs = DeploymentLog::query()
            ->with('deployment.device:id,hostname')
            ->latest('id')
            ->limit(8)
            ->get();

        $deploymentHistory = Deployment::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return view('dashboard', [
            'stats' => $stats,
            'latestDeployments' => $latestDeployments,
            'deviceStatus' => $deviceStatus,
            'recentLogs' => $recentLogs,
            'deploymentHistory' => $deploymentHistory,
            'currentRole' => $request->attributes->get('resolved_role', session('role', 'viewer')),
        ]);
    }
}
