<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\LLM\LLMAdapter;
use App\Services\LLM\LLMAdapterException;
use App\Services\LLM\LLMRequest;
use App\Services\LLM\LLMResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAskEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyst_can_ask_ai_with_rag_context_and_receive_provenance_response(): void
    {
        $analyst = User::factory()->create([
            'role' => 'analyst',
            'is_active' => true,
            'approved_at' => now(),
        ]);

        $response = $this
            ->actingAs($analyst)
            ->postJson('/api/ai/ask', [
                'question' => 'suricata exfiltration',
                'context' => 'Suricata detected exfiltration over TLS with unusual outbound bytes.',
                'context_title' => 'Suricata Triage',
                'context_path' => 'docs/suricata.md',
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'answer',
                'confidence',
                'sources' => [[
                    'source_id',
                    'source_title',
                    'excerpt',
                    'confidence',
                    'retrieved_at',
                ]],
            ])
            ->assertJsonPath('confidence', 1)
            ->assertJsonPath('sources.0.source_id', 'request_context')
            ->assertJsonPath('sources.0.source_title', 'Suricata Triage');
    }

    public function test_ai_ask_requires_analyst_permission(): void
    {
        $viewer = User::factory()->create([
            'role' => 'viewer',
            'is_active' => true,
            'approved_at' => now(),
        ]);

        $response = $this
            ->actingAs($viewer)
            ->withHeaders(['Accept' => 'application/json'])
            ->postJson('/api/ai/ask', [
                'question' => 'suricata exfiltration',
            ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Forbidden');
    }

    public function test_ai_ask_missing_evidence_returns_low_confidence_provenance(): void
    {
        $analyst = User::factory()->create([
            'role' => 'analyst',
            'is_active' => true,
            'approved_at' => now(),
        ]);

        $response = $this
            ->actingAs($analyst)
            ->postJson('/api/ai/ask', [
                'question' => 'unknown evidence request',
            ]);

        $response->assertOk()
            ->assertJsonPath('confidence', 0.1)
            ->assertJsonPath('sources.0.source_id', 'missing-evidence')
            ->assertJsonPath('sources.0.source_title', 'No retrieved evidence');
    }

    public function test_ai_ask_validates_question(): void
    {
        $analyst = User::factory()->create([
            'role' => 'analyst',
            'is_active' => true,
            'approved_at' => now(),
        ]);

        $response = $this
            ->actingAs($analyst)
            ->postJson('/api/ai/ask', [
                'context' => 'Suricata detected exfiltration.',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['question']);
    }

    public function test_ai_ask_handles_adapter_failures_safely(): void
    {
        $this->app->bind(LLMAdapter::class, fn (): LLMAdapter => new class implements LLMAdapter
        {
            public function complete(LLMRequest $request): LLMResponse
            {
                throw new LLMAdapterException('timeout');
            }
        });

        $analyst = User::factory()->create([
            'role' => 'analyst',
            'is_active' => true,
            'approved_at' => now(),
        ]);

        $response = $this
            ->actingAs($analyst)
            ->postJson('/api/ai/ask', [
                'question' => 'suricata exfiltration',
                'context' => 'Suricata detected exfiltration.',
            ]);

        $response->assertStatus(502)
            ->assertJsonPath('message', 'AI request failed');
    }
}
