<?php

namespace Tests\Feature;

use App\Models\DldsEvent;
use App\Models\User;
use App\Services\EventIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EventStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
    }

    public function test_stats_counts_events_by_type_correctly(): void
    {
        $service = app(EventIngestionService::class);

        // Create 10 alerts with various severities
        // 3 CRITICAL, 3 HIGH, 4 MEDIUM
        for ($i = 1; $i <= 10; $i++) {
            $service->ingest([
                'timestamp' => now()->toIso8601String(),
                'type' => 'alert',
                'severity' => $i <= 3 ? 'CRITICAL' : ($i <= 6 ? 'HIGH' : 'MEDIUM'),
                'description' => "Alert {$i}",
                'alert' => ['signature' => "Alert {$i}"],
            ]);
        }

        // Create 8 network events: 2 HIGH, 6 LOW
        for ($i = 1; $i <= 8; $i++) {
            $service->ingest([
                'timestamp' => now()->toIso8601String(),
                'type' => 'network',
                'severity' => $i <= 2 ? 'HIGH' : 'LOW',
                'description' => "Network {$i}",
                'src_ip' => '10.0.0.1',
                'dst_ip' => '8.8.8.8',
            ]);
        }

        // Create 5 process events: all LOW
        for ($i = 1; $i <= 5; $i++) {
            $service->ingest([
                'timestamp' => now()->toIso8601String(),
                'type' => 'process',
                'severity' => 'LOW',
                'description' => "Process {$i}",
                'pid' => 1000 + $i,
                'process_name' => "process{$i}.exe",
            ]);
        }

        $user = User::factory()->create([
            'role' => 'viewer',
            'is_active' => true,
            'approved_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/dlds/stats');

        $response->assertOk()
            ->assertJsonPath('total_events', 23)
            ->assertJsonPath('alert_events', 10)
            ->assertJsonPath('network_events', 8)
            ->assertJsonPath('process_events', 5)
            // Total severity counts: 3 CRITICAL, 5 HIGH (3 from alert + 2 from network), 4 MEDIUM (from alert), 11 LOW (6 from network + 5 from process)
            ->assertJsonPath('critical_severity', 3)
            ->assertJsonPath('high_severity', 5)
            ->assertJsonPath('medium_severity', 4)
            ->assertJsonPath('low_severity', 11);
    }

    public function test_public_stats_endpoint_returns_same_data(): void
    {
        $service = app(EventIngestionService::class);

        for ($i = 1; $i <= 5; $i++) {
            $service->ingest([
                'timestamp' => now()->toIso8601String(),
                'type' => 'alert',
                'severity' => 'HIGH',
                'description' => "Alert {$i}",
            ]);
        }

        $response = $this
            ->getJson('/api/dlds/public/stats');

        $response->assertOk()
            ->assertJsonPath('total_events', 5)
            ->assertJsonPath('alert_events', 5)
            ->assertJsonPath('high_severity', 5);
    }

    public function test_stats_handles_events_with_missing_type_id(): void
    {
        $service = app(EventIngestionService::class);

        // Create events with only severity, no explicit type
        for ($i = 1; $i <= 3; $i++) {
            $service->ingest([
                'timestamp' => now()->toIso8601String(),
                'severity' => $i === 1 ? 'CRITICAL' : ($i === 2 ? 'HIGH' : 'LOW'),
                'description' => "Event {$i}",
            ]);
        }

        $user = User::factory()->create([
            'role' => 'viewer',
            'is_active' => true,
            'approved_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/dlds/stats');

        $response->assertOk()
            ->assertJsonPath('total_events', 3)
            ->assertJsonPath('critical_severity', 1)
            ->assertJsonPath('high_severity', 1)
            ->assertJsonPath('low_severity', 1);
    }

    public function test_stats_with_mixed_explicit_and_heuristic_types(): void
    {
        $service = app(EventIngestionService::class);

        // Create explicit typed events
        for ($i = 1; $i <= 3; $i++) {
            $service->ingest([
                'timestamp' => now()->toIso8601String(),
                'type' => 'network',
                'severity' => 'LOW',
                'src_ip' => '10.0.0.1',
                'dst_ip' => '8.8.8.8',
                'description' => "Explicit network {$i}",
            ]);
        }

        // Create events with only network fields, no explicit type
        for ($i = 1; $i <= 2; $i++) {
            $service->ingest([
                'timestamp' => now()->toIso8601String(),
                'severity' => 'LOW',
                'src_ip' => '10.0.0.2',
                'dst_ip' => '1.1.1.1',
                'description' => "Heuristic network {$i}",
            ]);
        }

        $user = User::factory()->create([
            'role' => 'viewer',
            'is_active' => true,
            'approved_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/dlds/stats');

        $response->assertOk()
            ->assertJsonPath('total_events', 5)
            ->assertJsonPath('network_events', 5)
            ->assertJsonPath('low_severity', 5);
    }

    public function test_stats_does_not_double_count_events(): void
    {
        $service = app(EventIngestionService::class);

        // Create events that could match multiple heuristic rules
        for ($i = 1; $i <= 3; $i++) {
            $service->ingest([
                'timestamp' => now()->toIso8601String(),
                'type' => 'alert',
                'severity' => 'HIGH',
                'description' => "Alert {$i}",
            ]);
        }

        $user = User::factory()->create([
            'role' => 'viewer',
            'is_active' => true,
            'approved_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/dlds/stats');

        $total = $response->json('total_events');
        $alerts = $response->json('alert_events');
        $highs = $response->json('high_severity');

        $this->assertSame(3, $total);
        $this->assertSame(3, $alerts);
        $this->assertSame(3, $highs);
        
        // Total should equal sum of categories (approximately, with some overlap allowed)
        $this->assertGreaterThanOrEqual($total, $alerts + 0);
    }
}
