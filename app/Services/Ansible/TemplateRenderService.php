<?php

namespace App\Services\Ansible;

use App\Models\Deployment;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

class TemplateRenderService
{
    public function renderForDeployment(Deployment $deployment): ?string
    {
        $deployment->loadMissing(['configTemplate', 'device.hostVariables']);

        if (! $deployment->configTemplate) {
            return null;
        }

        $hostVariables = $deployment->device
            ->hostVariables
            ->mapWithKeys(fn ($item) => [$item->key => $this->castValue($item->value)])
            ->all();

        $context = [
            'device' => $deployment->device->toArray(),
            'host' => $hostVariables,
            'deployment' => $deployment->variables ?? [],
        ];

        $rendered = Blade::render($deployment->configTemplate->template_body, $context);

        $directory = rtrim(config('autoconfiglab.ansible_rendered_dir'), DIRECTORY_SEPARATOR);
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.'deployment-'.$deployment->id.'-'.now()->format('YmdHis').'.cfg';
        File::put($path, $rendered);

        return $path;
    }

    private function castValue(string $value): mixed
    {
        $trimmed = trim($value);

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        if ($trimmed === 'true' || $trimmed === 'false') {
            return $trimmed === 'true';
        }

        if (is_numeric($trimmed)) {
            return str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed;
        }

        return $trimmed;
    }
}
