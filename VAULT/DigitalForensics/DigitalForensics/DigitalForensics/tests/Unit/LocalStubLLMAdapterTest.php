<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\LLM\LLMRequest;
use App\Services\LLM\LocalStubLLMAdapter;
use PHPUnit\Framework\TestCase;

class LocalStubLLMAdapterTest extends TestCase
{
    public function test_stub_response_is_deterministic(): void
    {
        $adapter = new LocalStubLLMAdapter;
        $request = LLMRequest::user('Summarize suspicious outbound traffic.');

        $first = $adapter->complete($request);
        $second = $adapter->complete($request);

        $this->assertSame($first->content, $second->content);
        $this->assertSame($first->usage, $second->usage);
    }

    public function test_stub_can_return_structured_data(): void
    {
        $adapter = new LocalStubLLMAdapter;
        $request = LLMRequest::user(
            prompt: 'Classify this alert.',
            responseSchema: [
                'type' => 'object',
                'properties' => [
                    'summary' => ['type' => 'string'],
                ],
                'required' => ['summary'],
                'additionalProperties' => true,
            ],
        );

        $response = $adapter->complete($request);

        $this->assertSame('local_stub', $response->requireData()['adapter']);
        $this->assertJson($response->content);
    }
}
