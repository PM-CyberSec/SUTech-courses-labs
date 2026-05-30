<?php

declare(strict_types=1);

namespace App\Services\LLM;

final class LocalStubLLMAdapter implements LLMAdapter
{
    public function complete(LLMRequest $request): LLMResponse
    {
        $prompt = $this->lastUserMessage($request);
        $fingerprint = substr(hash('sha256', json_encode([
            'messages' => $request->messages,
            'model' => $request->model,
            'schema' => $request->responseSchema,
            'expects_json' => $request->expectsJson,
        ], JSON_THROW_ON_ERROR)), 0, 16);

        $summary = $this->summary($prompt);
        $data = null;
        $content = "local-stub:{$fingerprint} {$summary}";

        if ($request->expectsJson || $request->responseSchema !== null) {
            $data = [
                'adapter' => 'local_stub',
                'id' => $fingerprint,
                'answer' => $summary,
                'summary' => $summary,
                'confidence' => $this->confidence($request),
                'sources' => $request->evidence,
            ];
            $content = json_encode($data, JSON_THROW_ON_ERROR);
        }

        return new LLMResponse(
            content: $content,
            data: $data,
            model: $request->model ?? 'local-stub',
            finishReason: 'stop',
            usage: [
                'prompt_tokens' => str_word_count($prompt),
                'completion_tokens' => str_word_count($content),
                'total_tokens' => str_word_count($prompt) + str_word_count($content),
            ],
            raw: ['stub' => true],
        );
    }

    private function lastUserMessage(LLMRequest $request): string
    {
        for ($index = count($request->messages) - 1; $index >= 0; $index--) {
            if (($request->messages[$index]['role'] ?? null) === 'user') {
                return $request->messages[$index]['content'];
            }
        }

        return $request->messages[array_key_last($request->messages)]['content'];
    }

    private function summary(string $prompt): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $prompt) ?? '');

        if (strlen($normalized) <= 96) {
            return $normalized;
        }

        return substr($normalized, 0, 93).'...';
    }

    private function confidence(LLMRequest $request): float
    {
        if ($request->evidence === []) {
            return 1.0;
        }

        $scores = array_map(
            static fn (array $item): float => (float) $item['confidence'],
            $request->evidence,
        );

        return round(min($scores), 4);
    }
}
