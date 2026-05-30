<?php

namespace App\Http\Controllers;

use App\Models\Deployment;
use App\Models\Rollback;
use App\Services\Ansible\RollbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RollbackController extends Controller
{
    public function __construct(private readonly RollbackService $rollbackService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Rollback::query()->with(['deployment.device', 'requester:id,name,email,role']);

        if ($request->filled('deployment_id')) {
            $query->where('deployment_id', $request->integer('deployment_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        return response()->json($query->latest('id')->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'deployment_id' => ['required', 'exists:deployments,id'],
            'strategy' => ['nullable', Rule::in(['last_known_good', 'playbook', 'manual'])],
            'playbook_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'variables' => ['nullable', 'array'],
        ]);

        /** @var Deployment $deployment */
        $deployment = Deployment::query()->findOrFail($data['deployment_id']);
        $rollback = $this->rollbackService->execute($deployment, $data, $request->user()?->id);

        return response()->json($rollback, 201);
    }

    public function show(Rollback $rollback): JsonResponse
    {
        return response()->json($rollback->load(['deployment.logs', 'requester:id,name,email,role']));
    }
}
