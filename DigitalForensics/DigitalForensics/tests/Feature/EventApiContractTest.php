<?php

namespace Tests\Feature;

use App\Events\NewAlertEvent;
use App\Models\DldsEvent;
use App\Models\User;
use App\Services\EventIngestionService;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EventApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_model_to_array_is_the_canonical_api_contract(): void
    {
        Event::fake();

        $event = $this->ingestFixture([
            'timestamp' => '2026-04-18T10:15:30Z',
            'type' => 'alert',
            'severity' => 'CRITICAL',
            'description' => 'Contract fixture',
            'process_name' => 'python.exe',
            'src_ip' => '10.10.10.2',
            'dst_ip' => '8.8.8.8',
        ]);

        $payload = $event->toArray();

        $this->assertSame($payload, $event->toApiArray());
        $this->assertSame('alert', $payload['type']);
        $this->assertSame('CRITICAL', $payload['severity']);
        $this->assertSame('python.exe', $payload['process_name']);
        $this->assertArrayHasKey('event_time', $payload);
        $this->assertArrayHasKey('ai_evidence', $payload);
    }

    public function test_event_api_ignores_empty_filter_params_and_returns_json_without_redirects(): void
    {
        Event::fake();
        $this->ingestFixture([
            'timestamp' => '2026-04-18T10:15:30Z',
            'type' => 'network',
            'severity' => 'LOW',
            'description' => 'Empty filter fixture',
            'src_ip' => '10.0.0.2',
            'dst_ip' => '1.1.1.1',
        ]);

        $user = User::factory()->create([
            'role' => 'viewer',
            'is_active' => true,
            'approved_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->get('/api/dlds/events?type=&severity=&page=1');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.type', 'network');
    }

    public function test_guest_api_query_returns_json_401_instead_of_redirect(): void
    {
        $response = $this
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->get('/api/dlds/events');

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_sqlite_event_search_handles_event_time_without_mysql_date_format(): void
    {
        Event::fake();
        $this->ingestFixture([
            'timestamp' => '2026-04-18T10:15:30Z',
            'type' => 'alert',
            'severity' => 'HIGH',
            'description' => 'SQLite date search fixture',
        ]);

        $user = User::factory()->create([
            'role' => 'analyst',
            'is_active' => true,
            'approved_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->getJson('/api/dlds/events?search=2026-04-18');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.description', 'SQLite date search fixture');
    }

    public function test_realtime_event_is_queued_and_uses_event_contract(): void
    {
        Event::fake();
        $event = $this->ingestFixture([
            'timestamp' => '2026-04-18T10:15:30Z',
            'type' => 'alert',
            'severity' => 'HIGH',
            'description' => 'Broadcast contract fixture',
        ]);

        $broadcast = new NewAlertEvent($event);

        $this->assertInstanceOf(ShouldBroadcast::class, $broadcast);
        $this->assertInstanceOf(ShouldQueue::class, $broadcast);
        $this->assertSame($event->toArray(), $broadcast->broadcastWith()['event']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ingestFixture(array $payload): DldsEvent
    {
        /** @var EventIngestionService $service */
        $service = app(EventIngestionService::class);

        [$event] = $service->ingest($payload);

        $event->loadMissing(['eventType', 'process', 'alertCategory', 'severityLevel']);

        return $event;
    }
}
