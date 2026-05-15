<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_ingestion_key_configuration_returns_503(): void
    {
        config()->set('app.dlds_api_key', '');
        config()->set('app.dlds_hmac_secret', '');

        $response = $this->postJson('/api/dlds/events', [
            'type' => 'alert',
            'severity' => 'LOW',
            'description' => 'test',
        ]);

        $response->assertStatus(503);
    }

    public function test_missing_signature_header_returns_400(): void
    {
        config()->set('app.dlds_api_key', 'test-api-key');
        config()->set('app.dlds_hmac_secret', 'test-hmac-secret');

        $response = $this->postJson('/api/dlds/events', [
            'type' => 'alert',
            'severity' => 'LOW',
            'description' => 'missing signature',
        ], [
            'X-API-KEY' => 'test-api-key',
            'X-TIMESTAMP' => (string) Carbon::now()->timestamp,
        ]);

        $response->assertStatus(400);
    }

    public function test_invalid_signature_returns_401(): void
    {
        config()->set('app.dlds_api_key', 'test-api-key');
        config()->set('app.dlds_hmac_secret', 'test-hmac-secret');

        $response = $this->postJson('/api/dlds/events', [
            'type' => 'alert',
            'severity' => 'LOW',
            'description' => 'invalid signature',
        ], [
            'X-API-KEY' => 'test-api-key',
            'X-TIMESTAMP' => (string) Carbon::now()->timestamp,
            'X-SIGNATURE' => 'invalid-signature',
        ]);

        $response->assertStatus(401);
    }

    public function test_old_timestamp_returns_401(): void
    {
        config()->set('app.dlds_api_key', 'test-api-key');
        config()->set('app.dlds_hmac_secret', 'test-hmac-secret');
        config()->set('app.dlds_ingest_max_skew_seconds', 300);

        $payload = [
            'type' => 'alert',
            'severity' => 'LOW',
            'description' => 'stale payload',
        ];
        $timestamp = (string) Carbon::now()->subMinutes(10)->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.json_encode($payload, JSON_THROW_ON_ERROR), 'test-hmac-secret');

        $response = $this->postJson('/api/dlds/events', $payload, [
            'X-API-KEY' => 'test-api-key',
            'X-TIMESTAMP' => $timestamp,
            'X-SIGNATURE' => $signature,
        ]);

        $response->assertStatus(401);
    }

    public function test_valid_signed_request_succeeds(): void
    {
        \Illuminate\Support\Facades\Event::fake();
        config()->set('app.dlds_api_key', 'test-api-key');
        config()->set('app.dlds_hmac_secret', 'test-hmac-secret');

        $payload = [
            'type' => 'alert',
            'severity' => 'LOW',
            'description' => 'valid payload',
        ];

        $response = $this->postJson('/api/dlds/events', $payload, $this->signedHeaders($payload));

        $response->assertCreated()->assertJsonPath('status', 'stored');
    }

    public function test_guest_websocket_subscription_fails(): void
    {
        $response = $this
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-dlds-events',
                'socket_id' => '1234.5678',
            ]);

        $this->assertContains($response->getStatusCode(), [401, 403, 302]);
    }

    public function test_approved_analyst_websocket_subscription_succeeds(): void
    {
        Broadcast::setDefaultDriver('reverb');
        Broadcast::purge('log');
        Broadcast::purge('reverb');
        Broadcast::channel('dlds-events', function ($user) {
            return $user !== null
                && $user->isApproved()
                && $user->hasRole('admin', 'analyst');
        });

        $analyst = User::factory()->create([
            'role' => 'analyst',
            'is_active' => true,
            'approved_at' => now(),
        ]);

        $response = $this
            ->actingAs($analyst)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-dlds-events',
                'socket_id' => '1234.5678',
            ]);

        $response->assertOk()->assertJsonStructure(['auth']);
    }

    public function test_inactive_user_cannot_access_dashboard(): void
    {
        $inactive = User::factory()->create([
            'is_active' => false,
            'approved_at' => null,
        ]);

        $response = $this->actingAs($inactive)->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_admin_only_route(): void
    {
        $analyst = User::factory()->create([
            'role' => 'analyst',
            'is_active' => true,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($analyst)->get('/admin/health');

        $response->assertForbidden();
    }

    public function test_login_rate_limit_returns_429(): void
    {
        $user = User::factory()->create([
            'email' => 'lockout@example.com',
            'password' => bcrypt('StrongPass123'),
            'role' => 'analyst',
            'is_active' => true,
            'approved_at' => now(),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'WrongPass123',
            ]);
        }

        $response = $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'WrongPass123',
        ]);

        $response->assertStatus(429);
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
