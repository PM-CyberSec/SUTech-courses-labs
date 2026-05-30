<?php

namespace Tests\Feature;

use App\Models\DldsEvent;
use App\Models\User;
use App\Services\EventIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EventPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
    }

    public function test_pagination_meta_structure_is_correct(): void
    {
        $service = app(EventIngestionService::class);

        // Create 30 events
        for ($i = 1; $i <= 30; $i++) {
            $mod3 = $i % 3;
            $mod4 = $i % 4;
            $mod255 = $i % 255;
            $type = $mod3 === 0 ? 'alert' : ($mod3 === 1 ? 'network' : 'process');
            $severity = ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'][$mod4];
            
            $service->ingest([
                'timestamp' => now()->subMinutes(30 - $i)->toIso8601String(),
                'type' => $type,
                'severity' => $severity,
                'description' => "Test event {$i}",
                'pid' => $i,
                'process_name' => "process{$i}.exe",
                'src_ip' => "10.0.0.{$mod255}",
                'dst_ip' => "8.8.8.{$mod255}",
            ]);
        }

        $user = User::factory()->create([
            'role' => 'viewer',
            'is_active' => true,
            'approved_at' => now(),
        ]);

        // Test first page
        $response = $this
            ->actingAs($user)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->get('/api/dlds/events?per_page=10&page=1');

        $response->assertOk()
            ->assertJsonPath('meta.total', 30)
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 10)
            ->assertJsonCount(10, 'data');

        // Test last page
        $response = $this
            ->actingAs($user)
            ->get('/api/dlds/events?per_page=10&page=3');

        $response->assertOk()
            ->assertJsonPath('meta.page', 3)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.from', 21)
            ->assertJsonPath('meta.to', 30)
            ->assertJsonCount(10, 'data');

        // Test empty page beyond range
        $response = $this
            ->actingAs($user)
            ->get('/api/dlds/events?per_page=10&page=4');

        $response->assertOk()
            ->assertJsonPath('meta.page', 4)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonCount(0, 'data');
    }

    public function test_pagination_meta_with_filters(): void
    {
        $service = app(EventIngestionService::class);

        // Create 15 alerts and 15 network events
        for ($i = 1; $i <= 15; $i++) {
            $mod2 = $i % 2;
            $severity1 = $mod2 === 0 ? 'HIGH' : 'LOW';
            $severity2 = $mod2 === 0 ? 'MEDIUM' : 'LOW';
            
            $service->ingest([
                'timestamp' => now()->subMinutes(30 - $i)->toIso8601String(),
                'type' => 'alert',
                'severity' => $severity1,
                'description' => "Alert {$i}",
            ]);

            $service->ingest([
                'timestamp' => now()->subMinutes(30 - $i)->toIso8601String(),
                'type' => 'network',
                'severity' => $severity2,
                'description' => "Network {$i}",
                'src_ip' => '10.0.0.1',
                'dst_ip' => '8.8.8.8',
            ]);
        }

        $user = User::factory()->create([
            'role' => 'analyst',
            'is_active' => true,
            'approved_at' => now(),
        ]);

        // Filter by type=alert
        $response = $this
            ->actingAs($user)
            ->get('/api/dlds/events?type=alert&per_page=10');

        $response->assertOk()
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 10)
            ->assertJsonCount(10, 'data');

        $this->assertTrue(
            collect($response->json('data'))->every(fn ($e) => $e['type'] === 'alert'),
            'All data items should be alerts'
        );

        // Filter by type=network
        $response = $this
            ->actingAs($user)
            ->get('/api/dlds/events?type=network&per_page=10');

        $response->assertOk()
            ->assertJsonPath('meta.total', 15)
            ->assertJsonCount(10, 'data');

        $this->assertTrue(
            collect($response->json('data'))->every(fn ($e) => $e['type'] === 'network'),
            'All data items should be network'
        );
    }

    public function test_pagination_persists_filter_params(): void
    {
        $service = app(EventIngestionService::class);

        for ($i = 1; $i <= 20; $i++) {
            $severity = $i > 10 ? 'CRITICAL' : 'LOW';
            
            $service->ingest([
                'timestamp' => now()->subMinutes(20 - $i)->toIso8601String(),
                'type' => 'alert',
                'severity' => $severity,
                'description' => "Event {$i}",
            ]);
        }

        $user = User::factory()->create([
            'role' => 'viewer',
            'is_active' => true,
            'approved_at' => now(),
        ]);

        // Request with severity filter on page 1
        $response = $this
            ->actingAs($user)
            ->get('/api/dlds/events?severity=CRITICAL&per_page=5&page=1');

        $response->assertOk()
            ->assertJsonPath('meta.total', 10)
            ->assertJsonPath('meta.last_page', 2);

        // Next page should still have filter applied
        $response = $this
            ->actingAs($user)
            ->get('/api/dlds/events?severity=CRITICAL&per_page=5&page=2');

        $response->assertOk()
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.total', 10);

        $this->assertTrue(
            collect($response->json('data'))->every(fn ($e) => $e['severity'] === 'CRITICAL'),
            'All data items should have CRITICAL severity'
        );
    }

    public function test_public_api_returns_same_pagination_meta(): void
    {
        $service = app(EventIngestionService::class);

        for ($i = 1; $i <= 20; $i++) {
            $service->ingest([
                'timestamp' => now()->subMinutes(20 - $i)->toIso8601String(),
                'type' => 'network',
                'severity' => 'LOW',
                'description' => "Event {$i}",
                'src_ip' => '10.0.0.1',
                'dst_ip' => '8.8.8.8',
            ]);
        }

        $response = $this
            ->withHeaders(['Accept' => 'application/json'])
            ->get('/api/dlds/public/events?per_page=10&page=1');

        $response->assertOk()
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 10)
            ->assertJsonCount(10, 'data');
    }

    public function test_alert_network_and_process_endpoints_apply_consistent_scope_rules(): void
    {
        $service = app(EventIngestionService::class);

        $service->ingest([
            'timestamp' => now()->subMinutes(6)->toIso8601String(),
            'type' => 'alert',
            'severity' => 'HIGH',
            'description' => 'Suricata alert fixture',
            'alert_type' => 'Malware',
            'src_ip' => '10.0.0.10',
            'dst_ip' => '198.51.100.10',
        ]);

        $service->ingest([
            'timestamp' => now()->subMinutes(5)->toIso8601String(),
            'severity' => 'HIGH',
            'description' => 'Heuristic alert fixture',
        ]);

        $service->ingest([
            'timestamp' => now()->subMinutes(4)->toIso8601String(),
            'type' => 'network',
            'severity' => 'LOW',
            'description' => 'Zeek network fixture',
            'src_ip' => '10.0.0.20',
            'dst_ip' => '8.8.8.8',
        ]);

        $service->ingest([
            'timestamp' => now()->subMinutes(3)->toIso8601String(),
            'severity' => 'LOW',
            'description' => 'Heuristic network fixture',
            'src_ip' => '10.0.0.21',
            'dst_ip' => '1.1.1.1',
        ]);

        $service->ingest([
            'timestamp' => now()->subMinutes(2)->toIso8601String(),
            'type' => 'process',
            'severity' => 'LOW',
            'description' => 'Auditd process fixture',
            'pid' => 4242,
            'process_name' => 'python.exe',
            'file_path' => '/tmp/test.bin',
        ]);

        $service->ingest([
            'timestamp' => now()->subMinutes(1)->toIso8601String(),
            'severity' => 'LOW',
            'description' => 'Heuristic process fixture',
            'pid' => 5151,
            'process_name' => 'bash',
        ]);

        $user = User::factory()->create([
            'role' => 'viewer',
            'is_active' => true,
            'approved_at' => now(),
        ]);

        $alerts = $this->actingAs($user)->getJson('/api/dlds/public/alerts?per_page=25');
        $network = $this->actingAs($user)->getJson('/api/dlds/public/network?per_page=25');
        $processes = $this->actingAs($user)->getJson('/api/dlds/public/processes?per_page=25');

        $alerts->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.current_page', 1);
        $network->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.current_page', 1);
        $processes->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.current_page', 1);

        $this->assertSame(['alert', 'alert'], collect($alerts->json('data'))->pluck('type')->sort()->values()->all());
        $this->assertSame(['network', 'network'], collect($network->json('data'))->pluck('type')->sort()->values()->all());
        $this->assertSame(['process', 'process'], collect($processes->json('data'))->pluck('type')->sort()->values()->all());
    }

    public function test_web_pages_use_the_same_totals_as_api_scope_queries(): void
    {
        $service = app(EventIngestionService::class);

        $service->ingest([
            'timestamp' => now()->subMinutes(3)->toIso8601String(),
            'type' => 'network',
            'severity' => 'LOW',
            'description' => 'Network page fixture',
            'src_ip' => '10.0.0.30',
            'dst_ip' => '8.8.4.4',
        ]);

        $service->ingest([
            'timestamp' => now()->subMinutes(2)->toIso8601String(),
            'severity' => 'LOW',
            'description' => 'Heuristic network page fixture',
            'src_ip' => '10.0.0.31',
            'dst_ip' => '9.9.9.9',
        ]);

        $service->ingest([
            'timestamp' => now()->subMinute()->toIso8601String(),
            'type' => 'alert',
            'severity' => 'HIGH',
            'description' => 'Alert should not inflate network totals',
            'src_ip' => '10.0.0.32',
            'dst_ip' => '203.0.113.32',
            'alert_type' => 'Intrusion',
        ]);

        $user = User::factory()->create([
            'role' => 'viewer',
            'is_active' => true,
            'approved_at' => now(),
        ]);

        $apiResponse = $this->actingAs($user)->getJson('/api/dlds/public/network?per_page=25');
        $pageResponse = $this->actingAs($user)->get('/network');

        $apiResponse->assertOk()->assertJsonPath('meta.total', 2);
        $pageResponse->assertOk();

        $pagePaginator = $pageResponse->viewData('network');

        $this->assertSame(2, $pagePaginator->total());
        $this->assertSame($apiResponse->json('meta.total'), $pagePaginator->total());
    }
}
