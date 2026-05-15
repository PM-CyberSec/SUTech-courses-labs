<?php

namespace App\Providers;

use App\Services\LLM\LLMAdapter;
use App\Services\LLM\LLMAdapterException;
use App\Services\LLM\LocalStubLLMAdapter;
use App\Services\LLM\OpenAICompatibleLLMAdapter;
use App\Services\RAG\LocalKeywordRetriever;
use App\Services\RAG\RetrieverInterface;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LLMAdapter::class, function (): LLMAdapter {
            return match ((string) config('llm.driver', 'local_stub')) {
                'local_stub', 'stub', 'local' => app(LocalStubLLMAdapter::class),
                'openai', 'openai_compatible' => app(OpenAICompatibleLLMAdapter::class),
                default => throw new LLMAdapterException('Unsupported LLM driver: '.(string) config('llm.driver')),
            };
        });

        $this->app->bind(RetrieverInterface::class, LocalKeywordRetriever::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
