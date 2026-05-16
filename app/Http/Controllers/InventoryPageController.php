<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Services\Ansible\InventoryBuilderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryPageController extends Controller
{
    public function __construct(private readonly InventoryBuilderService $inventoryBuilder) {}

    public function index(Request $request): View
    {
        $inventories = Inventory::query()
            ->withCount('devices')
            ->with('devices:id,inventory_id,hostname,mgmt_ip,status')
            ->latest('id')
            ->paginate(15);

        return view('inventories.index', [
            'inventories' => $inventories,
            'currentRole' => $request->attributes->get('resolved_role', session('role', 'viewer')),
        ]);
    }

    public function create(Request $request): View
    {
        return view('inventories.create', [
            'currentRole' => $request->attributes->get('resolved_role', session('role', 'viewer')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:inventories,name'],
            'group_name' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'variables' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $variables = $this->parseJsonInput($data['variables'] ?? null);

        $inventory = Inventory::create([
            'name' => $data['name'],
            'group_name' => $data['group_name'] ?: null,
            'description' => $data['description'] ?? null,
            'variables' => $variables,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->inventoryBuilder->buildForInventory($inventory);

        return redirect()->route('inventories.index')->with('success', 'Inventory created successfully.');
    }

    public function edit(Request $request, Inventory $inventory): View
    {
        return view('inventories.edit', [
            'inventory' => $inventory,
            'variablesJson' => $inventory->variables ? json_encode($inventory->variables, JSON_PRETTY_PRINT) : '',
            'currentRole' => $request->attributes->get('resolved_role', session('role', 'viewer')),
        ]);
    }

    public function update(Request $request, Inventory $inventory): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:inventories,name,'.$inventory->id],
            'group_name' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'variables' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $variables = $this->parseJsonInput($data['variables'] ?? null);

        $inventory->update([
            'name' => $data['name'],
            'group_name' => $data['group_name'] ?: null,
            'description' => $data['description'] ?? null,
            'variables' => $variables,
            'is_active' => $request->boolean('is_active', false),
        ]);

        $this->inventoryBuilder->buildForInventory($inventory->fresh('devices.hostVariables'));

        return redirect()->route('inventories.index')->with('success', 'Inventory updated successfully.');
    }

    public function destroy(Inventory $inventory): RedirectResponse
    {
        $inventory->delete();

        return redirect()->route('inventories.index')->with('success', 'Inventory deleted successfully.');
    }

    private function parseJsonInput(?string $value): ?array
    {
        if (! $value) {
            return null;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
