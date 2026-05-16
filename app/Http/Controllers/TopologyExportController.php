<?php

namespace App\Http\Controllers;

use App\Models\Topology;
use App\Services\Topology\TopologyExportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Http\JsonResponse;

class TopologyExportController extends Controller
{
    public function __construct(private readonly TopologyExportService $exportService) {}

    public function json(Topology $topology): JsonResponse
    {
        $payload = $this->exportService->exportJson($topology);

        return response()->json($payload);
    }

    public function zip(Topology $topology): BinaryFileResponse
    {
        $path = $this->exportService->exportZip($topology);

        return response()->download($path, basename($path), [
            'Content-Type' => 'application/zip',
        ]);
    }
}