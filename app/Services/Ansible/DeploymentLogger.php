<?php

namespace App\Services\Ansible;

use App\Models\Deployment;
use App\Models\DeploymentLog;

class DeploymentLogger
{
    public function log(
        Deployment $deployment,
        string $stage,
        string $message,
        string $level = 'info',
        ?string $rawOutput = null,
        array $metadata = []
    ): DeploymentLog {
        return DeploymentLog::create([
            'deployment_id' => $deployment->id,
            'stage' => $stage,
            'level' => $level,
            'message' => $message,
            'raw_output' => $rawOutput,
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }
}
