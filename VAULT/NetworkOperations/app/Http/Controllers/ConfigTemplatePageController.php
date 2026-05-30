<?php

namespace App\Http\Controllers;

use App\Models\ConfigTemplate;
use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ConfigTemplatePageController extends Controller
{
    public function index(Request $request): View
    {
        $templates = ConfigTemplate::query()
            ->withCount('deployments')
            ->when($request->filled('category'), fn ($query) => $query->where('category', (string) $request->string('category')))
            ->when($request->filled('template_group'), fn ($query) => $query->where('template_group', (string) $request->string('template_group')))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('templates.index', [
            'templates' => $templates,
            'categories' => ['interface', 'vlan', 'routing', 'rollback', 'custom'],
            'groups' => ['switching', 'routing', 'security'],
            'currentRole' => $request->attributes->get('resolved_role', session('role', 'viewer')),
        ]);
    }

    public function create(Request $request): View
    {
        return view('templates.create', [
            'categories' => ['interface', 'vlan', 'routing', 'rollback', 'custom'],
            'groups' => ['switching', 'routing', 'security'],
            'devices' => Device::query()->orderBy('hostname')->get(['id', 'hostname']),
            'currentRole' => $request->attributes->get('resolved_role', session('role', 'viewer')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:config_templates,name'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:config_templates,slug'],
            'category' => ['required', 'in:interface,vlan,routing,rollback,custom'],
            'template_group' => ['nullable', 'in:switching,routing,security'],
            'description' => ['nullable', 'string'],
            'template_body' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        ConfigTemplate::create([
            'name' => $data['name'],
            'slug' => ($data['slug'] ?? null) ?: Str::slug($data['name']),
            'category' => $data['category'],
            'template_group' => $data['template_group'] ?? 'switching',
            'description' => $data['description'] ?? null,
            'template_body' => $data['template_body'],
            'version' => 1,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('templates.index')->with('success', 'Template created successfully.');
    }

    public function show(Request $request, ConfigTemplate $template): View
    {
        return view('templates.show', [
            'template' => $template->loadCount('deployments'),
            'devices' => Device::query()->orderBy('hostname')->get(['id', 'hostname']),
            'preview' => null,
            'currentRole' => $request->attributes->get('resolved_role', session('role', 'viewer')),
        ]);
    }

    public function preview(Request $request, ConfigTemplate $template): View
    {
        $request->validate([
            'device_id' => ['nullable', 'exists:devices,id'],
            'deployment_vars' => ['nullable', 'string'],
        ]);

        $device = null;
        $hostVars = [];
        if ($request->filled('device_id')) {
            $device = Device::query()->with('hostVariables')->find($request->integer('device_id'));
            if ($device) {
                $hostVars = $device->hostVariables->mapWithKeys(fn ($item) => [$item->variable_name => $item->variable_value])->all();
            }
        }

        $demoDevice = [
            'id' => 0,
            'hostname' => 'DEMO-SW-01',
            'mgmt_ip' => '192.168.1.100',
            'platform' => 'Catalyst 2960-X',
            'vendor' => 'Cisco',
            'inventory_id' => 1,
            'status' => 'active',
            'connection' => 'network_cli',
            'auth_username' => 'admin',
            'ssh_port' => 22,
        ];

        $deviceContext = $device ? $device->toArray() : $demoDevice;

        if (empty($hostVars)) {
            $hostVars = [
                'site_code' => 'NYC',
                'building' => 'HQ',
                'floor' => '2',
                'controller_ip' => '10.1.1.1',
            ];
        }

        $deploymentVars = [];
        if ($request->filled('deployment_vars')) {
            $decoded = json_decode((string) $request->string('deployment_vars'), true);
            if (is_array($decoded)) {
                $deploymentVars = $decoded;
            }
        }

        $mergedDeployment = array_merge([
            'vlan_id' => 10,
            'vlan_name' => 'USERS',
            'interface_name' => 'GigabitEthernet0/1',
            'ip_address' => '192.168.10.1',
            'subnet_mask' => '255.255.255.0',
            'gateway' => '192.168.10.1',
        ], $deploymentVars);

        $preview = Blade::render($template->template_body, [
            'device' => $deviceContext,
            'host' => $hostVars,
            'deployment' => $mergedDeployment,
        ]);

        return view('templates.show', [
            'template' => $template->loadCount('deployments'),
            'devices' => Device::query()->orderBy('hostname')->get(['id', 'hostname']),
            'preview' => $preview,
            'currentRole' => $request->attributes->get('resolved_role', session('role', 'viewer')),
        ]);
    }

    public function edit(Request $request, ConfigTemplate $template): View
    {
        return view('templates.edit', [
            'template' => $template,
            'categories' => ['interface', 'vlan', 'routing', 'rollback', 'custom'],
            'groups' => ['switching', 'routing', 'security'],
            'currentRole' => $request->attributes->get('resolved_role', session('role', 'viewer')),
        ]);
    }

    public function update(Request $request, ConfigTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:config_templates,name,'.$template->id],
            'slug' => ['nullable', 'string', 'max:120', 'unique:config_templates,slug,'.$template->id],
            'category' => ['required', 'in:interface,vlan,routing,rollback,custom'],
            'template_group' => ['nullable', 'in:switching,routing,security'],
            'description' => ['nullable', 'string'],
            'template_body' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $version = $template->template_body !== $data['template_body'] ? $template->version + 1 : $template->version;

        $template->update([
            'name' => $data['name'],
            'slug' => ($data['slug'] ?? null) ?: Str::slug($data['name']),
            'category' => $data['category'],
            'template_group' => $data['template_group'] ?? $template->template_group ?? 'switching',
            'description' => $data['description'] ?? null,
            'template_body' => $data['template_body'],
            'version' => $version,
            'is_active' => $request->boolean('is_active', false),
        ]);

        return redirect()->route('templates.index')->with('success', 'Template updated successfully.');
    }

    public function destroy(ConfigTemplate $template): RedirectResponse
    {
        $template->delete();

        return redirect()->route('templates.index')->with('success', 'Template deleted successfully.');
    }
}
