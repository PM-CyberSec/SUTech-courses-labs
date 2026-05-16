<?php

namespace App\Http\Controllers;

use App\Models\HostVariable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HostVariableController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = HostVariable::query()->with('device:id,hostname,mgmt_ip');

        if ($request->filled('device_id')) {
            $query->where('device_id', $request->integer('device_id'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->string('search');
            $query->where(function ($builder) use ($search): void {
                $builder->where('key', 'like', "%{$search}%")
                    ->orWhere('value', 'like', "%{$search}%");
            });
        }

        return response()->json($query->latest('id')->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'exists:devices,id'],
            'key' => [
                'required',
                'string',
                'max:120',
                Rule::unique('host_variables')->where(
                    fn ($query) => $query->where('device_id', $request->input('device_id'))
                ),
            ],
            'value' => ['required', 'string'],
            'is_secret' => ['nullable', 'boolean'],
        ]);

        $hostVariable = HostVariable::create($data)->load('device:id,hostname,mgmt_ip');

        return response()->json($hostVariable, 201);
    }

    public function show(HostVariable $hostVariable): JsonResponse
    {
        return response()->json($hostVariable->load('device'));
    }

    public function update(Request $request, HostVariable $hostVariable): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['sometimes', 'exists:devices,id'],
            'key' => [
                'sometimes',
                'string',
                'max:120',
                Rule::unique('host_variables')
                    ->where(fn ($query) => $query->where('device_id', $request->input('device_id', $hostVariable->device_id)))
                    ->ignore($hostVariable->id),
            ],
            'value' => ['sometimes', 'string'],
            'is_secret' => ['sometimes', 'boolean'],
        ]);

        $hostVariable->update($data);

        return response()->json($hostVariable->fresh()->load('device:id,hostname,mgmt_ip'));
    }

    public function destroy(HostVariable $hostVariable): JsonResponse
    {
        $hostVariable->delete();

        return response()->json([], 204);
    }
}
