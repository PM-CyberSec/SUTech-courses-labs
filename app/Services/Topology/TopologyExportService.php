<?php

namespace App\Services\Topology;

use App\Models\Topology;
use ZipArchive;

class TopologyExportService
{
    public function exportJson(Topology $topology): array
    {
        $topology->loadMissing(['devices.topologyInterfaces', 'links.sourceDevice', 'links.targetDevice', 'configs', 'validationResults']);

        return [
            'topology' => [
                'id' => $topology->id,
                'name' => $topology->name,
                'description' => $topology->description,
                'scenario_type' => $topology->scenario_type,
                'status' => $topology->status,
                'metadata' => $topology->metadata,
            ],
            'devices' => $topology->devices->map(function ($device): array {
                return [
                    'id' => $device->id,
                    'name' => $device->name ?? $device->hostname,
                    'type' => $device->type ?? $device->device_type,
                    'role' => $device->role,
                    'x_position' => $device->x_position,
                    'y_position' => $device->y_position,
                    'interfaces' => $device->topologyInterfaces->values(),
                ];
            })->values(),
            'links' => $topology->links->values(),
            'configs' => $topology->configs->values(),
            'validation_results' => $topology->validationResults->values(),
        ];
    }

    public function exportZip(Topology $topology): string
    {
        $topology->loadMissing(['configs.topologyDevice', 'validationResults']);
        $path = storage_path('app/exports/topology-'.$topology->id.'.zip');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $json = $this->exportJson($topology);
        $zip->addFromString('topology.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        foreach ($topology->configs as $config) {
            $filename = ($config->topologyDevice?->name ?? $config->topologyDevice?->hostname ?? 'device').'.txt';
            $zip->addFromString($filename, $config->generated_cli);
        }

        $validationText = $topology->validationResults->map(fn ($result) => '['.$result->severity.'] '.$result->category.': '.$result->message)->implode(PHP_EOL);
        $zip->addFromString('validation.txt', $validationText);
        $zip->close();

        return $path;
    }
}