<?php

declare(strict_types=1);

namespace App\Security;

use App\Models\User;

class PermissionRegistry
{
    /**
     * @var array<string, list<string>>
     */
    private const ROLE_PERMISSIONS = [
        UserRole::Admin->value => ['*'],
        UserRole::Analyst->value => [
            Permission::ViewDashboard->value,
            Permission::ViewEvents->value,
            Permission::ViewAlerts->value,
            Permission::ViewNetwork->value,
            Permission::ViewProcesses->value,
            Permission::InvokeLLM->value,
        ],
        UserRole::Viewer->value => [
            Permission::ViewDashboard->value,
            Permission::ViewEvents->value,
            Permission::ViewAlerts->value,
            Permission::ViewNetwork->value,
            Permission::ViewProcesses->value,
        ],
    ];

    public function allows(?User $user, string|Permission $permission): bool
    {
        if ($user === null || ! $user->isApproved()) {
            return false;
        }

        return $this->roleAllows((string) $user->role, $permission);
    }

    public function roleAllows(string|UserRole $role, string|Permission $permission): bool
    {
        $roleName = $role instanceof UserRole ? $role->value : strtolower(trim($role));
        $permissionName = Permission::normalize($permission);
        $permissions = self::ROLE_PERMISSIONS[$roleName] ?? [];

        return in_array('*', $permissions, true)
            || in_array($permissionName, $permissions, true);
    }

    /**
     * @return list<string>
     */
    public function permissionsForRole(string|UserRole $role): array
    {
        $roleName = $role instanceof UserRole ? $role->value : strtolower(trim($role));

        return self::ROLE_PERMISSIONS[$roleName] ?? [];
    }
}
