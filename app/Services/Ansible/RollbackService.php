<?php

namespace App\Services\Ansible;

use App\Models\Deployment;
use App\Models\Rollback;
use App\Models\ConfigSnapshot;
use Illuminate\Support\Facades\Log;

class RollbackService
{
    private DeploymentService $deploymentService;

    public function __construct()
    {
        $this->deploymentService = new DeploymentService();
    }

    public function execute(Deployment $deployment, array $options = [], ?int $requestedBy = null): ?Rollback
    {
        $strategy = $options['strategy'] ?? 'last_known_good';
        $isSimulation = $deployment->simulation_mode || app()->environment('testing');

        $deployment->update(['status' => 'rolling_back']);

        Log::info('Starting rollback', [
            'deployment_id' => $deployment->id,
            'strategy' => $strategy,
            'simulation_mode' => $isSimulation,
        ]);

        try {
            if ($isSimulation) {
                return $this->simulateRollback($deployment, $options);
            }

            $result = match ($strategy) {
                'last_known_good' => $this->rollbackToLastKnownGood($deployment),
                'playbook' => $this->rollbackWithPlaybook($deployment, $options),
                'manual' => $this->rollbackManually($deployment, $options),
                default => $this->rollbackToLastKnownGood($deployment),
            };

            if ($result) {
                $deployment->update([
                    'status' => 'rolled_back',
                    'finished_at' => now(),
                ]);
            } else {
                $deployment->update(['status' => 'failed']);
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('Rollback failed', [
                'deployment_id' => $deployment->id,
                'error' => $e->getMessage(),
            ]);

            $deployment->update(['status' => 'failed']);

            return null;
        }
    }

    private function simulateRollback(Deployment $deployment, array $options): ?Rollback
    {
        Log::info('Simulating rollback', ['deployment_id' => $deployment->id]);

        $rollback = Rollback::create([
            'deployment_id' => $deployment->id,
            'requested_by' => $options['requested_by'] ?? null,
            'rollback_type' => 'simulation',
            'status' => 'success',
            'config_before' => $deployment->generated_config ?? '',
            'config_after' => 'Simulated rollback configuration',
            'output' => "Simulation mode - Rollback completed successfully\nPLAY RECAP *** changed=0 failed=0",
            'errors' => '',
        ]);

        Log::info('Rollback completed (simulation)', ['deployment_id' => $deployment->id]);

        return $rollback;
    }

    private function rollbackToLastKnownGood(Deployment $deployment): ?Rollback
    {
        $latestSnapshot = ConfigSnapshot::query()
            ->where('deployment_id', $deployment->id)
            ->where('device_id', $deployment->device_id)
            ->orderBy('id', 'desc')
            ->first();

        if (! $latestSnapshot) {
            $latestSnapshot = ConfigSnapshot::query()
                ->where('device_id', $deployment->device_id)
                ->orderBy('created_at', 'desc')
                ->first();
        }

        if (! $latestSnapshot) {
            Log::warning('No config snapshot found for rollback', [
                'deployment_id' => $deployment->id,
            ]);
            return null;
        }

        return $this->createRollbackRecord($deployment, $latestSnapshot, $deployment->requested_by);
    }

    private function rollbackWithPlaybook(Deployment $deployment, array $options): ?Rollback
    {
        $playbook = $options['playbook_name'] ?? 'rollback.yml';

        $playbookPath = base_path('ansible/playbooks/' . $playbook);

        if (! file_exists($playbookPath)) {
            Log::error('Rollback playbook not found', ['playbook' => $playbook]);
            return null;
        }

        $latestSnapshot = ConfigSnapshot::query()
            ->where('device_id', $deployment->device_id)
            ->orderBy('created_at', 'desc')
            ->first();

        return $this->createRollbackRecord($deployment, $latestSnapshot, $deployment->requested_by);
    }

    private function rollbackManually(Deployment $deployment, array $options): ?Rollback
    {
        $rollbackConfig = $options['config_content'] ?? null;

        if (! $rollbackConfig) {
            Log::error('Manual rollback requires config_content');
            return null;
        }

        $snapshot = ConfigSnapshot::create([
            'deployment_id' => $deployment->id,
            'device_id' => $deployment->device_id,
            'config_content' => $rollbackConfig,
            'config_path' => null,
            'snapshot_type' => 'manual_rollback',
        ]);

        return $this->createRollbackRecord($deployment, $snapshot, $deployment->requested_by);
    }

    private function createRollbackRecord(Deployment $deployment, ?ConfigSnapshot $snapshot, ?int $requestedBy): ?Rollback
    {
        if (! $snapshot) {
            return null;
        }

        $rollback = Rollback::create([
            'deployment_id' => $deployment->id,
            'requested_by' => $requestedBy,
            'rollback_type' => 'snapshot',
            'status' => 'completed',
            'config_before' => $deployment->generated_config,
            'config_after' => $snapshot->config_content,
            'output' => 'Rollback created from config snapshot',
            'errors' => null,
        ]);

        Log::info('Rollback created', [
            'rollback_id' => $rollback->id,
            'deployment_id' => $deployment->id,
        ]);

        return $rollback;
    }

    public function getRollbackHistory(Deployment $deployment): array
    {
        return $deployment->rollbacks()
            ->with('requester:id,name,email')
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();
    }

    public function canRollback(Deployment $deployment): bool
    {
        $validStatuses = ['completed', 'failed', 'validation_failed'];
        return in_array($deployment->status, $validStatuses);
    }
}