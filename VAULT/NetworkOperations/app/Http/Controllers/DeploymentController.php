<?php

namespace App\Http\Controllers;

use App\Models\Deployment;
use App\Models\Device;
use App\Models\Inventory;
use App\Models\ConfigTemplate;
use App\Jobs\ProcessDeploymentJob;
use App\Services\Ansible\DeploymentService;
use App\Services\Ansible\RollbackService;
use App\Services\Smart\AIAssistantService;
use App\Services\Smart\ConfigGeneratorService;
use App\Services\Smart\ValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeploymentController extends Controller
{
    public function __construct(
        private readonly DeploymentService $deploymentService,
        private readonly RollbackService $rollbackService,
        private readonly ConfigGeneratorService $configGeneratorService,
        private readonly ValidationService $validationService,
        private readonly AIAssistantService $aiAssistantService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Deployment::query()->with([
            'device:id,hostname,mgmt_ip',
            'inventory:id,name,group_name',
            'configTemplate:id,name,slug,category',
            'requester:id,name,email,role',
        ])->withCount('logs');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        if ($request->filled('device_id')) {
            $query->where('device_id', $request->integer('device_id'));
        }

        return response()->json($query->latest('id')->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request): JsonResponse
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
            'variables' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
            'execute_now' => ['nullable', 'boolean'],
            'simulation_mode' => ['nullable', 'boolean'],
        ]);

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
            'payload' => $data['variables'] ?? [],
        ]);

        $configTemplate = ! empty($data['config_template_id'])
            ? ConfigTemplate::query()->find($data['config_template_id'])
            : ConfigTemplate::query()->where('is_active', true)->orderBy('id')->first();

        $deployment = Deployment::create([
            'device_id' => $data['device_id'],
            'inventory_id' => $data['inventory_id'] ?? null,
            'config_template_id' => $configTemplate?->id,
            'requested_by' => $request->user()?->id,
            'playbook_name' => $assistantPlan['playbook_name'],
            'variables' => array_replace_recursive($assistantPlan['payload'], $data['variables'] ?? []),
            'notes' => trim(($data['notes'] ?? '').PHP_EOL.$assistantPlan['summary']),
            'status' => 'pending',
            'precheck_status' => 'pending',
            'postcheck_status' => 'pending',
            'simulation_mode' => (bool) ($data['simulation_mode'] ?? false),
        ]);

        $device = $deployment->device()->with('inventory')->firstOrFail();
        $inventory = $deployment->inventory ?: $device->inventory;
        $vars = $deployment->variables ?? [];
        $deployment->update([
            'generated_config' => $this->configGeneratorService->generateCiscoConfig($device, $inventory, $vars),
            'validation_results' => array_merge(
                $this->validationService->validateForDeployment($device, array_merge($vars, [
                    'playbook_name' => $assistantPlan['playbook_name'],
                ])),
                [
                    'assistant' => [
                        'goal' => $assistantPlan['goal'],
                        'preset_key' => $assistantPlan['preset_key'],
                        'recommendations' => $assistantPlan['recommendations'],
                        'warnings' => $assistantPlan['warnings'],
                    ],
                ]
            ),
        ]);

        if ($request->boolean('execute_now')) {
            ProcessDeploymentJob::dispatch($deployment->id, $request->user()?->id)->onQueue('deployments');
            $deployment->refresh();
        }

        return response()->json($deployment->load(['device', 'inventory', 'configTemplate']), 201);
    }

    public function show(Deployment $deployment): JsonResponse
    {
        return response()->json($deployment->load([
            'device.hostVariables',
            'inventory',
            'configTemplate',
            'requester:id,name,email,role',
            'logs',
            'rollbacks',
        ]));
    }

    public function update(Request $request, Deployment $deployment): JsonResponse
    {
        $data = $request->validate([
            'playbook_name' => ['sometimes', 'string', 'max:255'],
            'variables' => ['sometimes', 'nullable', 'array'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::in(['pending', 'running', 'success', 'failed', 'rolled_back'])],
            'precheck_status' => ['sometimes', Rule::in(['pending', 'passed', 'failed', 'skipped'])],
            'postcheck_status' => ['sometimes', Rule::in(['pending', 'passed', 'failed', 'skipped'])],
        ]);

        $deployment->update($data);

        return response()->json($deployment->fresh()->load(['device', 'inventory', 'configTemplate']));
    }

    public function destroy(Deployment $deployment): JsonResponse
    {
        $deployment->delete();

        return response()->json([], 204);
    }

    public function execute(Request $request, Deployment $deployment): JsonResponse
    {
        ProcessDeploymentJob::dispatch($deployment->id, $request->user()?->id)->onQueue('deployments');
        $deployment->refresh();

        return response()->json($deployment);
    }

    public function rollback(Request $request, Deployment $deployment): JsonResponse
    {
        $data = $request->validate([
            'strategy' => ['nullable', Rule::in(['last_known_good', 'playbook', 'manual'])],
            'playbook_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'variables' => ['nullable', 'array'],
        ]);

        $rollback = $this->rollbackService->execute($deployment, $data, $request->user()?->id);

        return response()->json($rollback, 201);
    }
}
