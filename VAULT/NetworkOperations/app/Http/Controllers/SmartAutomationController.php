<?php

namespace App\Http\Controllers;

use App\Models\Deployment;
use App\Models\Device;
use App\Models\Inventory;
use App\Services\Smart\AIAssistantService;
use App\Services\Ansible\DeploymentService;
use App\Services\Smart\ConfigGeneratorService;
use App\Services\Smart\ValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SmartAutomationController extends Controller
{
    public function __construct(
        private readonly ConfigGeneratorService $configGeneratorService,
        private readonly ValidationService $validationService,
        private readonly DeploymentService $deploymentService,
        private readonly AIAssistantService $aiAssistantService
    ) {}

    public function show(Request $request, Device $device): View
    {
        $suggestions = $this->configGeneratorService->suggestForDevice($device);

        return view('smart.device-auto-config', [
            'device' => $device->load('inventory'),
            'inventories' => Inventory::query()->orderBy('name')->get(['id', 'name']),
            'suggestions' => $suggestions,
            'presets' => $this->aiAssistantService->presets(),
            'scenarioPresets' => $this->configGeneratorService->presetScenarios(),
            'generatedConfig' => null,
            'validation' => ['errors' => [], 'warnings' => []],
            'payloadJson' => '',
            'intentText' => '',
            'currentRole' => $request->attributes->get('resolved_role', session('role', 'viewer')),
        ]);
    }

    public function generate(Request $request, Device $device): View|RedirectResponse
    {
        $data = $request->validate([
            'inventory_id' => ['nullable', 'exists:inventories,id'],
            'payload_json' => ['nullable', 'string'],
            'intent_text' => ['nullable', 'string', 'max:1000'],
            'preset_key' => ['nullable', 'string', 'max:50'],
            'scenario_key' => ['nullable', 'string', 'max:50'],
            'playbook_name' => ['nullable', 'string', 'max:255'],
            'simulation_mode' => ['nullable', 'boolean'],
            'action' => ['nullable', 'string'],
        ]);

        $inventory = null;
        if (! empty($data['inventory_id'])) {
            $inventory = Inventory::query()->find($data['inventory_id']);
        } else {
            $inventory = $device->inventory;
        }

        $payload = [];
        if (! empty($data['payload_json'])) {
            $decoded = json_decode($data['payload_json'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $assistantPlan = $this->aiAssistantService->buildPlan($device, $inventory, [
            'intent_text' => $data['intent_text'] ?? '',
            'preset_key' => $data['preset_key'] ?? '',
            'scenario_key' => $data['scenario_key'] ?? '',
            'playbook_name' => $data['playbook_name'] ?? '',
            'payload' => $payload,
        ]);

        $payload = array_replace_recursive($assistantPlan['payload'], $payload);
        if (! empty($data['scenario_key'])) {
            $payload['scenario_key'] = $data['scenario_key'];
        }

        $generatedConfig = $this->configGeneratorService->generateCiscoConfig($device, $inventory, $payload);
        $validation = $this->validationService->validateForDeployment($device, array_merge($payload, [
            'playbook_name' => $assistantPlan['playbook_name'],
        ]));

        if (($data['action'] ?? '') === 'simulate_deployment') {
            $deployment = Deployment::create([
                'device_id' => $device->id,
                'inventory_id' => $inventory?->id,
                'config_template_id' => null,
                'playbook_name' => $assistantPlan['playbook_name'],
                'status' => 'pending',
                'precheck_status' => 'pending',
                'postcheck_status' => 'pending',
                'simulation_mode' => true,
                'variables' => $payload,
                'notes' => 'Created from Smart Auto Config generator. '.$assistantPlan['summary'],
            ]);

            $this->deploymentService->execute($deployment);

            return redirect()->route('deployments.show', $deployment)->with('success', 'Simulation deployment generated successfully.');
        }

        return view('smart.device-auto-config', [
            'device' => $device->load('inventory'),
            'inventories' => Inventory::query()->orderBy('name')->get(['id', 'name']),
            'suggestions' => $this->configGeneratorService->suggestForDevice($device),
            'presets' => $this->aiAssistantService->presets(),
            'scenarioPresets' => $this->configGeneratorService->presetScenarios(),
            'generatedConfig' => $generatedConfig,
            'validation' => $validation,
            'payloadJson' => $data['payload_json'] ?? '',
            'intentText' => $data['intent_text'] ?? '',
            'currentRole' => $request->attributes->get('resolved_role', session('role', 'viewer')),
        ]);
    }
}
