<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Inventory;
use App\Services\Ansible\InventoryBuilderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DevicePageController extends Controller
{
    public function __construct(private readonly InventoryBuilderService $inventoryBuilder) {}

    public function index(Request $request): View
    {
        $devices = Device::query()
            ->with('inventory:id,name')
            ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->string('status')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = (string) $request->string('search');
                $query->where(fn ($builder) => $builder
                    ->where('hostname', 'like', "%{$search}%")
                    ->orWhere('mgmt_ip', 'like', "%{$search}%"));
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('devices.index', [
            'devices' => $devices,
            'statuses' => ['provisioning', 'active', 'maintenance', 'disabled'],
            'currentRole' => $request->attributes->get('resolved_role', session('role', 'viewer')),
        ]);
    }

    public function create(Request $request): View
    {
        return view('devices.create', [
            'inventories' => Inventory::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => ['provisioning', 'active', 'maintenance', 'disabled'],
            'currentRole' => $request->attributes->get('resolved_role', session('role', 'viewer')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hostname' => ['required', 'string', 'max:120'],
            'ip_address' => ['required', 'ip', 'unique:devices,mgmt_ip'],
            'platform' => ['required', 'string', 'max:100'],
            'connection_type' => ['required', 'string', 'max:60'],
            'username' => ['required', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:provisioning,active,maintenance,disabled'],
            'inventory_id' => ['nullable', 'exists:inventories,id'],
            'ssh_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'vendor' => ['nullable', 'string', 'max:120'],
            'ansible_host' => ['nullable', 'string', 'max:120'],
        ]);

        $device = Device::create([
            'hostname' => $data['hostname'],
            'mgmt_ip' => $data['ip_address'],
            'platform' => $data['platform'],
            'connection' => $data['connection_type'],
            'auth_username' => $data['username'],
            'auth_password' => $data['password'] ?? null,
            'become_password' => $data['secret'] ?? null,
            'status' => $data['status'],
            'inventory_id' => $data['inventory_id'] ?? null,
            'ssh_port' => $data['ssh_port'] ?? 22,
            'vendor' => $data['vendor'] ?? null,
            'ansible_host' => $data['ansible_host'] ?? $data['ip_address'],
        ]);

        if ($device->inventory) {
            $this->inventoryBuilder->buildForInventory($device->inventory);
        }

        return redirect()->route('devices.index')->with('success', 'Device created successfully.');
    }

    public function edit(Request $request, Device $device): View
    {
        return view('devices.edit', [
            'device' => $device,
            'inventories' => Inventory::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => ['provisioning', 'active', 'maintenance', 'disabled'],
            'currentRole' => $request->attributes->get('resolved_role', session('role', 'viewer')),
        ]);
    }

    public function update(Request $request, Device $device): RedirectResponse
    {
        $oldInventoryId = $device->inventory_id;

        $data = $request->validate([
            'hostname' => ['required', 'string', 'max:120'],
            'ip_address' => ['required', 'ip', 'unique:devices,mgmt_ip,'.$device->id],
            'platform' => ['required', 'string', 'max:100'],
            'connection_type' => ['required', 'string', 'max:60'],
            'username' => ['required', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:provisioning,active,maintenance,disabled'],
            'inventory_id' => ['nullable', 'exists:inventories,id'],
            'ssh_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'vendor' => ['nullable', 'string', 'max:120'],
            'ansible_host' => ['nullable', 'string', 'max:120'],
        ]);

        $device->update([
            'hostname' => $data['hostname'],
            'mgmt_ip' => $data['ip_address'],
            'platform' => $data['platform'],
            'connection' => $data['connection_type'],
            'auth_username' => $data['username'],
            'auth_password' => $data['password'] ?? null,
            'become_password' => $data['secret'] ?? null,
            'status' => $data['status'],
            'inventory_id' => $data['inventory_id'] ?? null,
            'ssh_port' => $data['ssh_port'] ?? 22,
            'vendor' => $data['vendor'] ?? null,
            'ansible_host' => $data['ansible_host'] ?? $data['ip_address'],
        ]);

        if ($device->inventory) {
            $this->inventoryBuilder->buildForInventory($device->inventory);
        }

        if ($oldInventoryId && $oldInventoryId !== $device->inventory_id) {
            $oldInventory = Inventory::query()->find($oldInventoryId);
            if ($oldInventory) {
                $this->inventoryBuilder->buildForInventory($oldInventory);
            }
        }

        return redirect()->route('devices.index')->with('success', 'Device updated successfully.');
    }

    public function destroy(Device $device): RedirectResponse
    {
        $inventory = $device->inventory;
        $device->delete();

        if ($inventory) {
            $this->inventoryBuilder->buildForInventory($inventory->fresh());
        }

        return redirect()->route('devices.index')->with('success', 'Device deleted successfully.');
    }
}
