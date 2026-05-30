<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AskAiRequest;
use App\Services\AI\AiAskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AiAskController extends Controller
{
    public function __invoke(AskAiRequest $request, AiAskService $service): JsonResponse
    {
        try {
            return response()->json($service->ask($request->user(), $request->validated()));
        } catch (\Throwable $exception) {
            Log::warning('AI ask request failed safely', [
                'event_type' => 'ai.ask.failed',
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'user_id' => $request->user()?->id,
                'request_id' => $request->attributes->get('request_id') ?: $request->header('X-Request-ID'),
            ]);

            return response()->json([
                'message' => 'AI request failed',
            ], 502);
        }
    }
}
