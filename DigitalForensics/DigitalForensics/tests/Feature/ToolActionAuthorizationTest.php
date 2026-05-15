<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ToolActionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthorized_tool_action_access_is_blocked(): void
    {
        Route::middleware(['web', 'auth', 'approved', 'permission:tool.execute'])
            ->get('/_test/tool-action', fn () => response()->json(['status' => 'ok']));

        $viewer = User::factory()->create([
            'role' => 'viewer',
            'is_active' => true,
            'approved_at' => now(),
        ]);

        $response = $this
            ->actingAs($viewer)
            ->withHeaders(['Accept' => 'application/json'])
            ->get('/_test/tool-action');

        $response->assertForbidden()
            ->assertJsonPath('message', 'Forbidden');
    }

    public function test_authorized_tool_action_access_is_allowed(): void
    {
        Route::middleware(['web', 'auth', 'approved', 'permission:tool.execute'])
            ->get('/_test/admin-tool-action', fn () => response()->json(['status' => 'ok']));

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'approved_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->get('/_test/admin-tool-action');

        $response->assertOk()
            ->assertJsonPath('status', 'ok');
    }
}
