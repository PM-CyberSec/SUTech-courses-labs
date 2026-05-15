<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_pending_account_and_requires_approval(): void
    {
        config()->set('app.public_registration', true);

        $response = $this->post('/register', [
            'name' => 'SOC Analyst',
            'email' => 'analyst@example.com',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
        ]);

        $response->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseHas('users', [
            'email' => 'analyst@example.com',
            'role' => 'viewer',
            'is_active' => false,
            'approved_at' => null,
        ]);
    }

    public function test_user_can_login_and_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@example.com',
            'password' => bcrypt('StrongPass123'),
        ]);

        $login = $this->post('/login', [
            'email' => $user->email,
            'password' => 'StrongPass123',
        ]);

        $login->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $logout = $this->post('/logout');
        $logout->assertRedirect('/login');
        $this->assertGuest();
    }
}
