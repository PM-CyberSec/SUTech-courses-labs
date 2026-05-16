<?php

namespace App\Jobs;

use App\Models\Deployment;
use App\Services\Ansible\DeploymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessDeploymentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $deploymentId,
        public readonly ?int $requestedBy = null
    ) {}

    public function handle(DeploymentService $deploymentService): void
    {
        $deployment = Deployment::query()->find($this->deploymentId);
        if (! $deployment) {
            return;
        }

        $deploymentService->execute($deployment, $this->requestedBy);
    }
}
