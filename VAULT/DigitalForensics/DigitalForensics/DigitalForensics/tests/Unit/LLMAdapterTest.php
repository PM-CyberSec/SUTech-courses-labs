<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\LLM\LLMAdapter;
use App\Services\LLM\LLMRequest;
use App\Services\LLM\LLMResponse;
use App\Services\LLM\LocalStubLLMAdapter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LLMAdapterTest extends TestCase
{
    public function test_adapter_interface_returns_response_contract(): void
    {
        $adapter = new LocalStubLLMAdapter;

        $this->assertInstanceOf(LLMAdapter::class, $adapter);
        $this->assertInstanceOf(
            LLMResponse::class,
            $adapter->complete(LLMRequest::user('Explain this alert.')),
        );
    }

    public function test_request_requires_at_least_one_message(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LLMRequest(messages: []);
    }
}
