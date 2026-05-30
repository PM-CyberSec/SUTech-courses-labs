<?php

namespace App\Http\Controllers;

use App\Models\ConfigSnapshot;
use App\Models\ConfigTemplate;
use App\Models\Deployment;
use App\Models\Device;
use App\Models\Inventory;
use App\Jobs\ProcessDeploymentJob;
use App\Services\Smart\AIAssistantService;
use App\Services\Ansible\DeploymentService;
use App\Services\Ansible\RollbackService;
use App\Services\Smart\ConfigGeneratorService;
use App\Services\Smart\ValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class DeploymentPageController extends Controller
{
    public function __construct(
        private readonly DeploymentService $deploymentService,
        private readonly RollbackService $rollbackService,
        private readonly ConfigGeneratorService $configGeneratorService,
        private readonly ValidationService $validationService,
        private readonly AIAssistantService $aiAssistantService
    ) {}

    public function index(Request $request): View
    {
        $deployments = Deployment::query()
            ->with(['device:id,hostname,mgmt_ip', 'configTemplate:id,name', 'inventory:id,name'])
            ->withCount('logs')
            ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->string('status')))
            ->when($request->filled('device_id'), fn ($query) => $query->where('device_id', $request->integer('device_id')))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('deployments.index', [
            'deployments' => $deployments,
            'devices' => Device::query()->orderBy('hostname')->get(['id', 'hostname']),
            'statuses' => ['pending', 'running', 'success', 'failed', 'rolled_back'],
            'currentRole' => $request->attributes->get('resolved_role', session('role', 'viewer')),
        ]);
    }

    public function create(Request $request): View
    {
        return view('deployments.create', [
            'devices' => Device::query()->orderBy('hostname')->get(['id', 'hostname', 'inventory_id']),
            'inventories' => Inventory::query()->orderBy('name')->get(['id', 'name']),
            'templates' => ConfigTemplate::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'category']),
            'playbooks' => $this->availablePlaybooks(),
            'presets' => $this->aiAssistantService->presets(),
            'scenarioPresets' => $this->configGeneratorService->presetScenarios(),
            'wizardSteps' => ['Device', 'Goal', 'Inputs', 'Preview', 'Deploy'],
            'currentRole' => $request->attributes->get('resolved_role', session('role', 'viewer')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'exists:devices,id'],
            'inventory_id' => ['nullable', 'exists:inventories,id'],
            'config_template_id' => ['nullable', 'exists:config_templates,id'],
            'playbook_name' => ['nullable', 'string', 'max:255'],
            'goal' => ['nullable', 'string', 'max:100'],
            'preset_key' => ['nullable', 'string', 'max:50'],
            'scenario_key' => ['nullable', 'string', 'max:50'],
            'intent_text' => ['nullable', 'string', 'max:1000'],
            'variables' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'execute_now' => ['nullable', 'boolean'],
            'simulation_mode' => ['nullable', 'boolean'],
        ]);

        $vars = [];
        if (! empty($data['variables'])) {
            $decoded = json_decode($data['variables'], true);
            if (is_array($decoded)) {
                $vars = $decoded;
            }
        }

        $device = Device::query()->findOrFail($data['device_id']);
        $inventory = ! empty($data['inventory_id'])
            ? Inventory::query()->find($data['inventory_id'])
            : $device->inventory;

        $assistantPlan = $this->aiAssistantService->buildPlan($device, $inventory, [
            'intent_text' => $data['intent_text'] ?? '',
            'goal' => $data['goal'] ?? '',
            'preset_key' => $data['preset_key'] ?? '',
            'scenario_key' => $data['scenario_key'] ?? '',
            'playbook_name' => $data['playbook_name'] ?? '',
            'payload' => $vars,
        ]);

        $vars = array_replace_recursive($assistantPlan['payload'], $vars);
        if (! empty($data['scenario_key'])) {
            $vars['scenario_key'] = $data['scenario_key'];
        }

        $configTemplate = ! empty($data['config_template_id'])
            ? ConfigTemplate::query()->find($data['config_template_id'])
            : ConfigTemplate::query()->where('is_active', true)->orderBy('id')->first();

        $deployment = Deployment::create([
            'device_id' => $data['device_id'],
            'inventory_id' => $data['inventory_id'] ?? null,
            'config_template_id' => $configTemplate?->id,
            'requested_by' => null,
            'playbook_name' => $assistantPlan['playbook_name'],
            'variables' => $vars === [] ? null : $vars,
            'notes' => trim(($data['notes'] ?? '').PHP_EOL.$assistantPlan['summary']),
            'status' => 'pending',
            'precheck_status' => 'pending',
            'postcheck_status' => 'pending',
            'simulation_mode' => $request->boolean('simulation_mode'),
        ]);

        $generated = '';
        try {
            $generated = $this->configGeneratorService->generateCiscoConfig($device, $inventory, $vars) ?? '';
        } catch (\Throwable $e) {
            $generated = '';
        }
        $validation = $this->validationService->validateForDeployment($device, array_merge($vars, [
            'playbook_name' => $assistantPlan['playbook_name'],
        ]));
        $deployment->update([
            'generated_config' => $generated,
            'validation_results' => array_merge($validation, [
                'assistant' => [
                    'goal' => $assistantPlan['goal'],
                    'preset_key' => $assistantPlan['preset_key'],
                    'recommendations' => $assistantPlan['recommendations'],
                    'warnings' => $assistantPlan['warnings'],
                ],
            ]),
        ]);

        if ($request->boolean('execute_now')) {
            try {
                ProcessDeploymentJob::dispatch($deployment->id, $request->user()?->id)->onQueue('deployments');
                $deployment->refresh();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Job dispatch failed: ' . $e->getMessage());
            }
        }

        return redirect()->route('deployments.show', $deployment)->with('success', 'Deployment request created successfully.');
    }

    public function show(Request $request, Deployment $deployment): View
    {
        $deployment->load([
            'device.inventory',
            'inventory',
            'configTemplate',
            'logs',
            'rollbacks',
        ]);

        $lastWorkingSnapshot = ConfigSnapshot::query()
            ->where('device_id', $deployment->device_id)
            ->where('snapshot_type', 'successful')
            ->where(function ($query) use ($deployment): void {
                $query->whereNull('deployment_id')->orWhere('deployment_id', '!=', $deployment->id);
            })
            ->latest('id')
            ->first();

        $configDiff = $this->buildConfigDiff(
            $lastWorkingSnapshot?->config_body,
            $deployment->generated_config
        );

        return view('deployments.show', [
            'deployment' => $deployment,
            'lastWorkingSnapshot' => $lastWorkingSnapshot,
            'configDiff' => $configDiff,
            'currentRole' => $request->attributes->get('resolved_role', session('role', 'viewer')),
        ]);
    }

    public function execute(Deployment $deployment): RedirectResponse
    {
        ProcessDeploymentJob::dispatch($deployment->id)->onQueue('deployments');

        return redirect()->route('deployments.show', $deployment)->with('success', 'Deployment executed.');
    }

    public function rollback(Request $request, Deployment $deployment): RedirectResponse
    {
        $data = $request->validate([
            'strategy' => ['nullable', 'in:last_known_good,playbook,manual'],
            'playbook_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'variables' => ['nullable', 'string'],
        ]);

        $vars = [];
        if (! empty($data['variables'])) {
            $decoded = json_decode($data['variables'], true);
            if (is_array($decoded)) {
                $vars = $decoded;
            }
        }

        $this->rollbackService->execute($deployment, [
            'strategy' => $data['strategy'] ?? 'playbook',
            'playbook_name' => $data['playbook_name'] ?? 'rollback.yml',
            'notes' => $data['notes'] ?? null,
            'variables' => $vars,
        ]);

        return redirect()->route('deployments.show', $deployment)->with('success', 'Rollback executed.');
    }

    public function destroy(Deployment $deployment): RedirectResponse
    {
        $deployment->delete();

        return redirect()->route('deployments.index')->with('success', 'Deployment deleted successfully.');
    }

    /**
     * @return array<int, string>
     */
    private function availablePlaybooks(): array
    {
        $dir = rtrim((string) config('autoconfiglab.ansible_playbook_dir'), DIRECTORY_SEPARATOR);
        if (! File::isDirectory($dir)) {
            return ['interface_config.yml', 'vlan_setup.yml', 'routing_config.yml', 'rollback.yml'];
        }

        return collect(File::files($dir))
            ->map(fn ($file) => $file->getFilename())
            ->filter(fn ($name) => str_ends_with($name, '.yml') || str_ends_with($name, '.yaml'))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{type:string,text:string}>
     */
    private function buildConfigDiff(?string $oldConfig, ?string $newConfig): array
    {
        $oldLines = $oldConfig ? preg_split('/\R/', $oldConfig) : [];
        $newLines = $newConfig ? preg_split('/\R/', $newConfig) : [];

        $oldLines = is_array($oldLines) ? $oldLines : [];
        $newLines = is_array($newLines) ? $newLines : [];

        $removed = array_values(array_diff($oldLines, $newLines));
        $added = array_values(array_diff($newLines, $oldLines));

        $diff = [];
        foreach ($removed as $line) {
            $diff[] = ['type' => 'removed', 'text' => $line];
        }
        foreach ($added as $line) {
            $diff[] = ['type' => 'added', 'text' => $line];
        }

        return $diff;
    }
}
