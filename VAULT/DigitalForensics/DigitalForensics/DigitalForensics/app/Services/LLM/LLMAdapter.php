<?php

declare(strict_types=1);

namespace App\Services\LLM;

interface LLMAdapter
{
    public function complete(LLMRequest $request): LLMResponse;
}
