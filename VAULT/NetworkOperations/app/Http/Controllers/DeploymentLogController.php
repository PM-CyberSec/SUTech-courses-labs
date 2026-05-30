<?php

namespace App\Http\Controllers;

use App\Models\DeploymentLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeploymentLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DeploymentLog::query()->with('deployment:id,device_id,status,playbook_name');

        if ($request->filled('deployment_id')) {
            $query->where('deployment_id', $request->integer('deployment_id'));
        }

        if ($request->filled('stage')) {
            $query->where('stage', (string) $request->string('stage'));
        }

        if ($request->filled('level')) {
            $query->where('level', (string) $request->string('level'));
        }

        return response()->json($query->latest('id')->paginate($request->integer('per_page', 25)));
    }

    public function show(DeploymentLog $deploymentLog): JsonResponse
    {
        return response()->json($deploymentLog->load('deployment'));
    }
}
