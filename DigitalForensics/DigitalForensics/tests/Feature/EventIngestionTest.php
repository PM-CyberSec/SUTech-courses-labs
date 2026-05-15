<?php

namespace Tests\Feature;

use App\Models\DldsEvent;
use App\Services\EventIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class EventIngestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Event::fake();
    }

    public function test_legacy_payload_is_mapped_to_relational_schema(): void
    {
        config()->set('app.dlds_api_key', 'test-api-key');
        config()->set('app.dlds_hmac_secret', 'test-hmac-secret');

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

        $response = $this->postJson('/api/dlds/events', $payload, $this->signedHeaders($payload));

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
        config()->set('app.dlds_api_key', 'test-api-key');
        config()->set('app.dlds_hmac_secret', 'test-hmac-secret');

        $payload = [
            'timestamp' => '2026-04-18T10:15:30Z',
            'type' => 'network',
            'severity' => 'LOW',
            'description' => 'Repeated network event',
        ];

        $first = $this->postJson('/api/dlds/events', $payload, $this->signedHeaders($payload));
        $second = $this->postJson('/api/dlds/events', $payload, $this->signedHeaders($payload));

        $first->assertCreated()->assertJsonPath('status', 'stored');
        $second->assertOk()->assertJsonPath('status', 'duplicate');
        $this->assertSame(1, DldsEvent::query()->count());
    }

    public function test_nested_source_destination_payload_is_mapped_to_network_fields(): void
    {
        config()->set('app.dlds_api_key', 'test-api-key');
        config()->set('app.dlds_hmac_secret', 'test-hmac-secret');

        $payload = [
            'timestamp' => '2026-04-18T10:15:30Z',
            'event_type' => 'dns',
            'severity' => 'LOW',
            'message' => 'DNS query observed',
            'source' => [
                'ip' => '10.1.2.3',
                'port' => 5353,
            ],
            'destination' => [
                'address' => '8.8.8.8',
                'p' => 53,
            ],
        ];

        $response = $this->postJson('/api/dlds/events', $payload, $this->signedHeaders($payload));

        $response->assertCreated()
            ->assertJsonPath('event.type', 'network')
            ->assertJsonPath('event.src_ip', '10.1.2.3')
            ->assertJsonPath('event.src_port', 5353)
            ->assertJsonPath('event.dst_ip', '8.8.8.8')
            ->assertJsonPath('event.dst_port', 53)
            ->assertJsonPath('event.description', 'DNS query observed');

        $event = DldsEvent::query()->firstOrFail();

        $this->assertNotNull($event->event_hash);
        $this->assertSame(5353, $event->src_port);
        $this->assertSame(53, $event->dst_port);
    }

    public function test_suricata_alert_payload_aliases_are_mapped_to_alert_fields(): void
    {
        config()->set('app.dlds_api_key', 'test-api-key');
        config()->set('app.dlds_hmac_secret', 'test-hmac-secret');

        $payload = [
            'timestamp' => '2026-04-18T10:15:30Z',
            'event_type' => 'alert',
            'src_ip' => '10.0.0.5',
            'src_port' => 4444,
            'dest_ip' => '203.0.113.10',
            'dest_port' => 443,
            'alert' => [
                'signature' => 'ET MALWARE Possible Exfiltration',
                'category' => 'Potential Corporate Privacy Violation',
                'severity' => 2,
            ],
        ];

        $response = $this->postJson('/api/dlds/events', $payload, $this->signedHeaders($payload));

        $response->assertCreated()
            ->assertJsonPath('event.type', 'alert')
            ->assertJsonPath('event.severity', 'HIGH')
            ->assertJsonPath('event.src_ip', '10.0.0.5')
            ->assertJsonPath('event.dst_ip', '203.0.113.10')
            ->assertJsonPath('event.dst_port', 443)
            ->assertJsonPath('event.alert_type', 'ET MALWARE Possible Exfiltration')
            ->assertJsonPath('event.description', 'ET MALWARE Possible Exfiltration (Potential Corporate Privacy Violation)');

        $event = DldsEvent::query()->firstOrFail();

        $this->assertNotNull($event->event_hash);
        $this->assertNotNull($event->alert_type_id);
    }

    public function test_high_attack_alert_is_overridden_to_non_benign_classification(): void
    {
        config()->set('app.dlds_api_key', 'test-api-key');
        config()->set('app.dlds_hmac_secret', 'test-hmac-secret');

        $payload = [
            'timestamp' => '2026-04-18T10:15:30Z',
            'type' => 'alert',
            'severity' => 'HIGH',
            'alert_type' => 'GPL ATTACK_RESPONSE id check returned root',
            'description' => 'GPL ATTACK_RESPONSE id check returned root',
            'ai_label' => 'benign',
            'confidence' => 0.96,
            'anomaly_score' => 0.96,
            'ai_reason' => 'Model predicted benign behavior.',
        ];

        $response = $this->postJson('/api/dlds/events', $payload, $this->signedHeaders($payload));

        $response->assertCreated()
            ->assertJsonPath('event.type', 'alert');

        $this->assertNotSame('benign', $response->json('event.ai_label'));

        $event = DldsEvent::query()->firstOrFail();

        $this->assertNotSame('benign', $event->ai_label);
        $this->assertGreaterThanOrEqual(0.80, (float) $event->confidence);
        $this->assertGreaterThanOrEqual(0.80, (float) $event->anomaly_score);
        $this->assertStringContainsString('override', strtolower((string) $event->ai_reason));
    }

    public function test_process_payload_maps_auditd_style_fields_to_process_event(): void
    {
        config()->set('app.dlds_api_key', 'test-api-key');
        config()->set('app.dlds_hmac_secret', 'test-hmac-secret');

        $payload = [
            'timestamp' => '1777911110.460',
            'type' => 'process',
            'process' => 'bash',
            'pid' => 9001,
            'path' => '/usr/bin/bash',
            'message' => 'Auditd execve observed',
            'severity' => 0,
            'ai_label' => 'benign',
            'confidence' => 0.94,
            'ai_evidence' => ['Traffic conforms to known benign profiles.'],
        ];

        $response = $this->postJson('/api/dlds/events', $payload, $this->signedHeaders($payload));

        $response->assertCreated()
            ->assertJsonPath('event.type', 'process')
            ->assertJsonPath('event.process_name', 'bash')
            ->assertJsonPath('event.pid', 9001)
            ->assertJsonPath('event.file_path', '/usr/bin/bash')
            ->assertJsonPath('event.description', 'Auditd execve observed');

        $event = DldsEvent::query()->firstOrFail();

        $this->assertNotNull($event->event_type_id);
        $this->assertNotNull($event->process_id);
        $this->assertSame(9001, $event->pid);
        $this->assertSame('/usr/bin/bash', $event->file_path);
    }

    public function test_ingestion_logs_missing_critical_network_fields_without_blocking_event(): void
    {
        Log::spy();

        /** @var EventIngestionService $service */
        $service = app(EventIngestionService::class);

        [$event, $isDuplicate] = $service->ingest([
            'timestamp' => '2026-04-18T10:15:30Z',
            'type' => 'network',
            'severity' => 'LOW',
            'description' => 'Network event missing endpoints',
        ]);

        $this->assertFalse($isDuplicate);
        $this->assertSame('network', $event->type);

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('DLDS event payload missing normalized fields', \Mockery::on(
                static fn (array $context): bool => $context['event_type'] === 'dlds.ingest.data_quality'
                    && in_array('src_ip', $context['missing_fields'], true)
                    && in_array('dst_ip', $context['missing_fields'], true)
            ));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function signedHeaders(array $payload): array
    {
        $body = (string) json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) Carbon::now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'test-hmac-secret');

        return [
            'X-API-KEY' => 'test-api-key',
            'X-TIMESTAMP' => $timestamp,
            'X-SIGNATURE' => $signature,
        ];
    }
}
