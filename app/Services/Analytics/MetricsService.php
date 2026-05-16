<?php

namespace App\Services\Analytics;

use App\Models\Deployment;
use App\Models\DeploymentLog;
use Illuminate\Support\Facades\DB;

class MetricsService
{
    public function summarizeDeployments(): array
    {
        $totalDeployments = Deployment::count();

        $completed = Deployment::where('status', 'completed')->count();
        $failed = Deployment::where('status', 'failed')->count();
        $running = Deployment::where('status', 'running')->count();
        $pending = Deployment::where('status', 'pending')->count();

        $successRate = $totalDeployments > 0
            ? round(($completed / $totalDeployments) * 100, 1)
            : 0;

        $failureRate = $totalDeployments > 0
            ? round(($failed / $totalDeployments) * 100, 1)
            : 0;

        $avgExecutionTime = $this->calculateAverageExecutionTime();

        $timeSavedEstimate = $this->estimateTimeSaved($completed);

        return [
            'total_deployments' => $totalDeployments,
            'completed_deployments' => $completed,
            'failed_deployments' => $failed,
            'running_deployments' => $running,
            'pending_deployments' => $pending,
            'success_rate' => $successRate,
            'failure_rate' => $failureRate,
            'avg_execution_time' => $avgExecutionTime,
            'estimated_time_saved' => $timeSavedEstimate,
        ];
    }

    public function getDeploymentTrends(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $deploymentsByDay = Deployment::query()
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
            )
            ->where('created_at', '>=', $startDate)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        return $deploymentsByDay->toArray();
    }

    public function getDeviceMetrics(): array
    {
        $deviceStats = Deployment::query()
            ->select(
                'device_id',
                DB::raw('COUNT(*) as total_deployments'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed"),
                DB::raw('MAX(created_at) as last_deployment')
            )
            ->whereNotNull('device_id')
            ->groupBy('device_id')
            ->get();

        return $deviceStats->toArray();
    }

    public function getLogMetrics(): array
    {
        $totalLogs = DeploymentLog::count();

        $logsByLevel = DeploymentLog::query()
            ->select('level', DB::raw('COUNT(*) as total'))
            ->groupBy('level')
            ->pluck('total', 'level')
            ->toArray();

        $recentErrors = DeploymentLog::query()
            ->where('level', 'error')
            ->with('deployment.device:id,hostname')
            ->latest('id')
            ->limit(10)
            ->get();

        return [
            'total_logs' => $totalLogs,
            'logs_by_level' => $logsByLevel,
            'recent_errors' => $recentErrors->toArray(),
        ];
    }

    private function calculateAverageExecutionTime(): ?string
    {
        $deployments = Deployment::query()
            ->whereNotNull('started_at')
            ->whereNotNull('finished_at')
            ->get();

        if ($deployments->isEmpty()) {
            return null;
        }

        $totalSeconds = 0;
        $count = 0;

        foreach ($deployments as $deployment) {
            $duration = $deployment->finished_at->diffInSeconds($deployment->started_at);
            $totalSeconds += $duration;
            $count++;
        }

        if ($count === 0) {
            return null;
        }

        $avgSeconds = round($totalSeconds / $count);

        if ($avgSeconds < 60) {
            return "{$avgSeconds}s";
        }

        if ($avgSeconds < 3600) {
            return round($avgSeconds / 60, 1) . 'm';
        }

        return round($avgSeconds / 3600, 1) . 'h';
    }

    private function estimateTimeSaved(int $completedDeployments): int
    {
        $avgManualConfigTime = 15;
        $avgAutomatedTime = 2;

        $timeSavedPerDeployment = $avgManualConfigTime - $avgAutomatedTime;

        return $completedDeployments * $timeSavedPerDeployment;
    }

    public function getRollbackMetrics(): array
    {
        $totalRollbacks = \App\Models\Rollback::count();
        $successfulRollbacks = \App\Models\Rollback::where('status', 'completed')->count();
        $failedRollbacks = \App\Models\Rollback::where('status', 'failed')->count();

        return [
            'total_rollbacks' => $totalRollbacks,
            'successful_rollbacks' => $successfulRollbacks,
            'failed_rollbacks' => $failedRollbacks,
            'rollback_rate' => $totalRollbacks > 0
                ? round(($failedRollbacks / $totalRollbacks) * 100, 1)
                : 0,
        ];
    }

    public function getQueueMetrics(): array
    {
        $pendingJobs = \Illuminate\Support\Facades\DB::table('jobs')
            ->whereNull('reserved_at')
            ->count();

        $failedJobs = \Illuminate\Support\Facades\DB::table('failed_jobs')
            ->count();

        return [
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => $failedJobs,
        ];
    }
}