<?php

declare(strict_types=1);

namespace App\Services\LLM;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;

final class OpenAICompatibleLLMAdapter implements LLMAdapter
{
    public function complete(LLMRequest $request): LLMResponse
    {
        $apiKey = (string) config('llm.openai.api_key', '');
        if ($apiKey === '') {
            throw new LLMAdapterException('The OpenAI-compatible LLM adapter is missing an API key.');
        }

        $http = Http::acceptJson()
            ->asJson()
            ->withToken($apiKey)
            ->timeout((int) config('llm.openai.timeout_seconds', 30))
            ->retry(
                max(1, (int) config('llm.openai.retry_attempts', 2) + 1),
                (int) config('llm.openai.retry_delay_ms', 250),
                throw: false,
            );

        $organization = (string) config('llm.openai.organization', '');
        if ($organization !== '') {
            $http = $http->withHeader('OpenAI-Organization', $organization);
        }

        $project = (string) config('llm.openai.project', '');
        if ($project !== '') {
            $http = $http->withHeader('OpenAI-Project', $project);
        }

        try {
            $response = $http->post($this->endpoint(), $this->payload($request));
        } catch (ConnectionException $exception) {
            throw new LLMAdapterException(
                'The OpenAI-compatible LLM request failed to connect: '.$exception->getMessage(),
                previous: $exception,
            );
        }

        if ($response->failed()) {
            throw new LLMAdapterException(sprintf(
                'The OpenAI-compatible LLM request failed with HTTP %d: %s',
                $response->status(),
                Str::limit($response->body(), 500),
            ));
        }

        $raw = $response->json();
        if (! is_array($raw)) {
            throw new LLMAdapterException('The OpenAI-compatible LLM response was not valid JSON.');
        }

        $content = data_get($raw, 'choices.0.message.content');
        if (! is_string($content)) {
            throw new LLMAdapterException('The OpenAI-compatible LLM response did not include message content.');
        }

        $data = null;
        if ($request->expectsJson || $request->responseSchema !== null) {
            try {
                $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new LLMAdapterException(
                    'The OpenAI-compatible LLM response content was not valid structured JSON.',
                    previous: $exception,
                );
            }

            if (! is_array($decoded)) {
                throw new LLMAdapterException('The OpenAI-compatible LLM structured response was not an object.');
            }

            $data = $decoded;
        }

        $usage = data_get($raw, 'usage');

        return new LLMResponse(
            content: $content,
            data: $data,
            model: is_string(data_get($raw, 'model')) ? data_get($raw, 'model') : null,
            finishReason: is_string(data_get($raw, 'choices.0.finish_reason')) ? data_get($raw, 'choices.0.finish_reason') : null,
            usage: is_array($usage) ? $usage : null,
            raw: $raw,
        );
    }

    private function endpoint(): string
    {
        return rtrim((string) config('llm.openai.base_url', 'https://api.openai.com/v1'), '/').'/chat/completions';
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(LLMRequest $request): array
    {
        $payload = [
            'model' => $request->model ?: (string) config('llm.openai.model', 'gpt-4.1-mini'),
            'messages' => $request->messages,
            'temperature' => $request->temperature,
        ];

        if ($request->responseSchema !== null) {
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => (string) ($request->metadata['schema_name'] ?? 'dlds_response'),
                    'schema' => $request->responseSchema,
                    'strict' => true,
                ],
            ];
        } elseif ($request->expectsJson) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        return $payload;
    }
}
