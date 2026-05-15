<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Security\Permission;
use App\Security\PermissionRegistry;
use App\Security\UserRole;
use Tests\TestCase;

class RbacPermissionTest extends TestCase
{
    public function test_admin_role_has_all_permissions(): void
    {
        $registry = new PermissionRegistry;

        $this->assertTrue($registry->roleAllows(UserRole::Admin, Permission::ExecuteTool));
        $this->assertTrue($registry->roleAllows(UserRole::Admin, Permission::ManageUsers));
    }

    public function test_viewer_can_read_but_cannot_invoke_llm_or_tools(): void
    {
        $registry = new PermissionRegistry;
        $viewer = new User([
            'role' => UserRole::Viewer->value,
            'is_active' => true,
            'approved_at' => now(),
        ]);

        $this->assertTrue($registry->allows($viewer, Permission::ViewEvents));
        $this->assertFalse($registry->allows($viewer, Permission::InvokeLLM));
        $this->assertFalse($registry->allows($viewer, Permission::ExecuteTool));
    }

    public function test_inactive_user_has_no_permissions(): void
    {
        $registry = new PermissionRegistry;
        $analyst = new User([
            'role' => UserRole::Analyst->value,
            'is_active' => false,
            'approved_at' => null,
        ]);

        $this->assertFalse($registry->allows($analyst, Permission::ViewEvents));
        $this->assertFalse($registry->allows($analyst, Permission::InvokeLLM));
    }
}
