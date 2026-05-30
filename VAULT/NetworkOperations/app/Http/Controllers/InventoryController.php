<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Services\Ansible\InventoryBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryBuilderService $inventoryBuilder) {}

    public function index(Request $request): JsonResponse
    {
        $query = Inventory::query()->withCount('devices');

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->string('search');
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('group_name', 'like', "%{$search}%");
            });
        }

        return response()->json($query->latest('id')->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:inventories,name'],
            'group_name' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $inventory = Inventory::create($data)->loadCount('devices');
        $this->inventoryBuilder->buildForInventory($inventory);

        return response()->json($inventory, 201);
    }

    public function show(Inventory $inventory): JsonResponse
    {
        return response()->json($inventory->load(['devices.hostVariables', 'deployments']));
    }

    public function update(Request $request, Inventory $inventory): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120', 'unique:inventories,name,'.$inventory->id],
            'group_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string'],
            'variables' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $inventory->update($data);
        $this->inventoryBuilder->buildForInventory($inventory->fresh('devices.hostVariables'));

        return response()->json($inventory->fresh()->loadCount('devices'));
    }

    public function destroy(Inventory $inventory): JsonResponse
    {
        $inventory->delete();

        return response()->json([], 204);
    }
}
