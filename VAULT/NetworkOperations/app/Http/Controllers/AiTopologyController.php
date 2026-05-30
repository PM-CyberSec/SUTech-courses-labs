<?php

namespace App\Http\Controllers;

use App\Models\Topology;
use App\Services\Topology\AiTopologyParserService;
use App\Services\Topology\TopologyGenerationService;
use App\Services\Topology\TopologyExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AiTopologyController extends Controller
{
    public function __construct(
        private readonly AiTopologyParserService $parser,
        private readonly TopologyGenerationService $generationService,
        private readonly TopologyExportService $exportService
    ) {}

    public function index(Request $request): View
    {
        return view('ai-topology.index', [
            'presets' => $this->parser->presetScenarios(),
            'currentRole' => $request->attributes->get('resolved_role', session('role', 'viewer')),
            'topology' => null,
            'canvasJson' => json_encode(['devices' => [], 'links' => [], 'configs' => [], 'validation' => []], JSON_UNESCAPED_SLASHES),
            'jsonExport' => null,
            'validationResults' => [],
            'simulationSteps' => [],
            'selectedDevice' => null,
        ]);
    }

    public function generate(Request $request): RedirectResponse|View
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'preset_key' => ['nullable', 'string', 'max:50'],
            'expert_blueprint_json' => ['nullable', 'string'],
        ]);

        $expertOverrides = [];
        if (! empty($data['expert_blueprint_json'])) {
            $decoded = json_decode($data['expert_blueprint_json'], true);
            if (! is_array($decoded)) {
                throw ValidationException::withMessages([
                    'expert_blueprint_json' => 'Expert blueprint JSON must be valid JSON.',
                ]);
            }

            $expertOverrides = $decoded;
        }

        $result = $this->generationService->generate(
            $data['prompt'],
            $data['preset_key'] ?? null,
            $expertOverrides,
            $request->user()?->id
        );

        if (! empty($result['validation']['errors'])) {
            return redirect()->route('ai-topology.show', $result['topology'])->withErrors($result['validation']['errors']);
        }

        return redirect()->route('ai-topology.show', $result['topology'])->with('success', 'Topology generated successfully.');
    }

    public function show(Request $request, Topology $topology): View
    {
        $topology->loadMissing(['devices.topologyInterfaces', 'links.sourceDevice', 'links.targetDevice', 'configs.topologyDevice', 'validationResults']);
        $jsonExport = $this->exportService->exportJson($topology);
        $canvas = $this->makeCanvasPayload($topology);

        return view('ai-topology.index', [
            'presets' => $this->parser->presetScenarios(),
            'currentRole' => $request->attributes->get('resolved_role', session('role', 'viewer')),
            'topology' => $topology,
            'canvasJson' => json_encode($canvas, JSON_UNESCAPED_SLASHES),
            'jsonExport' => json_encode($jsonExport, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'validationResults' => $topology->validationResults->values(),
            'simulationSteps' => $topology->metadata['simulation_steps'] ?? [],
            'selectedDevice' => $topology->devices->first(),
        ]);
    }

    private function makeCanvasPayload(Topology $topology): array
    {
        return [
            'devices' => $topology->devices->map(function ($device): array {
                return [
                    'id' => $device->id,
                    'name' => $device->name ?? $device->hostname,
                    'type' => $device->type ?? $device->device_type,
                    'role' => $device->role,
                    'x' => $device->x_position ?? 0,
                    'y' => $device->y_position ?? 0,
                    'interfaces' => $device->topologyInterfaces->map(fn ($interface) => [
                        'name' => $interface->name,
                        'ip_address' => $interface->ip_address,
                        'subnet_mask' => $interface->subnet_mask,
                        'vlan_id' => $interface->vlan_id,
                        'mode' => $interface->mode,
                    ])->values(),
                ];
            })->values(),
            'links' => $topology->links->map(function ($link): array {
                return [
                    'source_device' => $link->sourceDevice?->name ?? $link->fromDevice?->hostname,
                    'target_device' => $link->targetDevice?->name ?? $link->toDevice?->hostname,
                    'source_interface' => $link->source_interface ?? $link->from_interface_name,
                    'target_interface' => $link->target_interface ?? $link->to_interface_name,
                    'cable_type' => $link->cable_type ?? $link->link_type,
                    'status' => $link->status ?? 'planned',
                ];
            })->values(),
            'configs' => $topology->configs->map(fn ($config) => [
                'device' => $config->topologyDevice?->name ?? $config->topologyDevice?->hostname,
                'cli' => $config->generated_cli,
            ])->values(),
            'validation' => $topology->validationResults->map(fn ($result) => [
                'severity' => $result->severity,
                'category' => $result->category,
                'message' => $result->message,
                'suggested_fix' => $result->suggested_fix,
            ])->values(),
        ];
    }
}