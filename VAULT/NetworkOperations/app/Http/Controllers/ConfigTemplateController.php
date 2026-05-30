<?php

namespace App\Http\Controllers;

use App\Models\ConfigTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ConfigTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ConfigTemplate::query()->withCount('deployments');

        if ($request->filled('category')) {
            $query->where('category', (string) $request->string('category'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('template_group')) {
            $query->where('template_group', (string) $request->string('template_group'));
        }

        return response()->json($query->latest('id')->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:config_templates,name'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:config_templates,slug'],
            'category' => ['required', Rule::in(['interface', 'vlan', 'routing', 'rollback', 'custom'])],
            'template_group' => ['required', Rule::in(['switching', 'routing', 'security'])],
            'description' => ['nullable', 'string'],
            'template_body' => ['required', 'string'],
            'version' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['version'] = $data['version'] ?? 1;

        $template = ConfigTemplate::create($data)->loadCount('deployments');

        return response()->json($template, 201);
    }

    public function show(ConfigTemplate $configTemplate): JsonResponse
    {
        return response()->json($configTemplate->load(['deployments.device']));
    }

    public function update(Request $request, ConfigTemplate $configTemplate): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120', 'unique:config_templates,name,'.$configTemplate->id],
            'slug' => ['sometimes', 'nullable', 'string', 'max:120', 'unique:config_templates,slug,'.$configTemplate->id],
            'category' => ['sometimes', Rule::in(['interface', 'vlan', 'routing', 'rollback', 'custom'])],
            'template_group' => ['sometimes', Rule::in(['switching', 'routing', 'security'])],
            'description' => ['sometimes', 'nullable', 'string'],
            'template_body' => ['sometimes', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('name', $data) && ! array_key_exists('slug', $data)) {
            $data['slug'] = Str::slug($data['name']);
        }

        if (array_key_exists('template_body', $data) && $data['template_body'] !== $configTemplate->template_body) {
            $data['version'] = $configTemplate->version + 1;
        }

        $configTemplate->update($data);

        return response()->json($configTemplate->fresh()->loadCount('deployments'));
    }

    public function destroy(ConfigTemplate $configTemplate): JsonResponse
    {
        $configTemplate->delete();

        return response()->json([], 204);
    }
}
