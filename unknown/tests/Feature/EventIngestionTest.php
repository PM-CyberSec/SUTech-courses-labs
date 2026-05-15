<?php

namespace Tests\Feature;

use App\Models\DldsEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_payload_is_mapped_to_relational_schema(): void
    {
        $payload = [
            'timestamp' => '2026-04-18T10:15:30Z',
            'type' => 'alert',
            'pid' => 1452,
            'process_name' => 'python.exe',
            'file' => '/tmp/secrets.txt',
            'src_ip' => '10.0.0.2',
            'src_port' => 5123,
            'dst_ip' => '8.8.8.8',
            'dst_port' => 443,
            'bytes_sent' => 2300,
            'alert_type' => 'Data Leak',
            'severity' => 'HIGH',
            'description' => 'Detected exfiltration pattern',
        ];

        $response = $this->postJson('/api/dlds/events', $payload);

        $response->assertCreated()
            ->assertJsonPath('status', 'stored')
            ->assertJsonPath('event.type', 'alert')
            ->assertJsonPath('event.severity', 'HIGH')
            ->assertJsonPath('event.file_path', '/tmp/secrets.txt');

        $event = DldsEvent::query()->firstOrFail();

        $this->assertNotNull($event->event_type_id);
        $this->assertNotNull($event->process_id);
        $this->assertNotNull($event->alert_type_id);
        $this->assertGreaterThan(0, $event->severity_id);
        $this->assertSame('/tmp/secrets.txt', $event->file_path);
    }

    public function test_duplicate_payload_returns_duplicate_status(): void
    {
        $payload = [
            'timestamp' => '2026-04-18T10:15:30Z',
            'type' => 'network',
            'severity' => 'LOW',
            'description' => 'Repeated network event',
        ];

        $first = $this->postJson('/api/dlds/events', $payload);
        $second = $this->postJson('/api/dlds/events', $payload);

        $first->assertCreated()->assertJsonPath('status', 'stored');
        $second->assertOk()->assertJsonPath('status', 'duplicate');
        $this->assertSame(1, DldsEvent::query()->count());
    }
}
