<?php

return [
    'driver' => env('LLM_DRIVER', 'local_stub'),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY', ''),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
        'organization' => env('OPENAI_ORGANIZATION', ''),
        'project' => env('OPENAI_PROJECT', ''),
        'timeout_seconds' => (int) env('OPENAI_TIMEOUT_SECONDS', 30),
        'retry_attempts' => (int) env('OPENAI_RETRY_ATTEMPTS', 2),
        'retry_delay_ms' => (int) env('OPENAI_RETRY_DELAY_MS', 250),
    ],
];
