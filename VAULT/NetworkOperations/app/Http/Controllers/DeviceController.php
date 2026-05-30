<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Inventory;
use App\Services\Ansible\InventoryBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    public function __construct(private readonly InventoryBuilderService $inventoryBuilder) {}

    public function index(Request $request): JsonResponse
    {
        $query = Device::query()->with(['inventory:id,name,group_name', 'hostVariables']);

        if ($request->filled('inventory_id')) {
            $query->where('inventory_id', $request->integer('inventory_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->string('search');
            $query->where(function ($builder) use ($search): void {
                $builder->where('hostname', 'like', "%{$search}%")
                    ->orWhere('mgmt_ip', 'like', "%{$search}%");
            });
        }

        return response()->json($query->latest('id')->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'inventory_id' => ['nullable', 'exists:inventories,id'],
            'hostname' => ['required', 'string', 'max:120'],
            'mgmt_ip' => ['required', 'ip', 'unique:devices,mgmt_ip'],
            'ansible_host' => ['nullable', 'string', 'max:120'],
            'ssh_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'platform' => ['required', 'string', 'max:100'],
            'vendor' => ['nullable', 'string', 'max:120'],
            'auth_username' => ['required', 'string', 'max:120'],
            'auth_password' => ['nullable', 'string', 'max:255'],
            'become_password' => ['nullable', 'string', 'max:255'],
            'connection' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', Rule::in(['provisioning', 'active', 'maintenance', 'disabled'])],
            'metadata' => ['nullable', 'array'],
        ]);

        $data['ansible_host'] ??= $data['mgmt_ip'];

        $device = Device::create($data)->load(['inventory:id,name,group_name', 'hostVariables']);

        if ($device->inventory) {
            $this->inventoryBuilder->buildForInventory($device->inventory);
        }

        return response()->json($device, 201);
    }

    public function show(Device $device): JsonResponse
    {
        return response()->json($device->load(['inventory', 'hostVariables', 'deployments']));
    }

    public function update(Request $request, Device $device): JsonResponse
    {
        $data = $request->validate([
            'inventory_id' => ['sometimes', 'nullable', 'exists:inventories,id'],
            'hostname' => ['sometimes', 'string', 'max:120'],
            'mgmt_ip' => ['sometimes', 'ip', Rule::unique('devices', 'mgmt_ip')->ignore($device->id)],
            'ansible_host' => ['sometimes', 'nullable', 'string', 'max:120'],
            'ssh_port' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'platform' => ['sometimes', 'string', 'max:100'],
            'vendor' => ['sometimes', 'nullable', 'string', 'max:120'],
            'auth_username' => ['sometimes', 'string', 'max:120'],
            'auth_password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'become_password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'connection' => ['sometimes', 'string', 'max:60'],
            'status' => ['sometimes', Rule::in(['provisioning', 'active', 'maintenance', 'disabled'])],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        $oldInventoryId = $device->inventory_id;
        $device->update($data);

        if ($device->inventory) {
            $this->inventoryBuilder->buildForInventory($device->inventory);
        }

        if ($oldInventoryId && $oldInventoryId !== $device->inventory_id) {
            $oldInventory = Inventory::query()->find($oldInventoryId);
            if ($oldInventory) {
                $this->inventoryBuilder->buildForInventory($oldInventory->fresh('devices.hostVariables'));
            }
        }

        return response()->json($device->fresh(['inventory:id,name,group_name', 'hostVariables']));
    }

    public function destroy(Device $device): JsonResponse
    {
        $inventory = $device->inventory;
        $device->delete();

        if ($inventory) {
            $this->inventoryBuilder->buildForInventory($inventory->fresh('devices.hostVariables'));
        }

        return response()->json([], 204);
    }
}
