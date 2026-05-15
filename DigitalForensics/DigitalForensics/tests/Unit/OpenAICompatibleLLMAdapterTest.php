<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\LLM\LLMRequest;
use App\Services\LLM\OpenAICompatibleLLMAdapter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAICompatibleLLMAdapterTest extends TestCase
{
    public function test_openai_compatible_adapter_parses_structured_response(): void
    {
        config()->set('llm.openai.api_key', 'test-key');
        config()->set('llm.openai.base_url', 'https://llm.example.test/v1');
        config()->set('llm.openai.model', 'test-model');
        config()->set('llm.openai.timeout_seconds', 5);
        config()->set('llm.openai.retry_attempts', 1);

        Http::fake([
            'https://llm.example.test/v1/chat/completions' => Http::response([
                'model' => 'test-model',
                'choices' => [[
                    'finish_reason' => 'stop',
                    'message' => [
                        'role' => 'assistant',
                        'content' => '{"verdict":"suspicious","confidence":0.91}',
                    ],
                ]],
                'usage' => [
                    'prompt_tokens' => 5,
                    'completion_tokens' => 7,
                    'total_tokens' => 12,
                ],
            ]),
        ]);

        $response = (new OpenAICompatibleLLMAdapter)->complete(
            new LLMRequest(
                messages: [['role' => 'user', 'content' => 'Classify this event.']],
                expectsJson: true,
            ),
        );

        $this->assertSame('suspicious', $response->requireData()['verdict']);
        $this->assertSame('test-model', $response->model);
        $this->assertSame('stop', $response->finishReason);

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['model'] === 'test-model'
            && $request['response_format']['type'] === 'json_object');
    }
}
