<?php

declare(strict_types=1);

namespace App\Security;

enum Permission: string
{
    case ViewDashboard = 'dashboard.view';
    case ViewEvents = 'events.view';
    case ViewAlerts = 'alerts.view';
    case ViewNetwork = 'network.view';
    case ViewProcesses = 'processes.view';
    case ViewAdminHealth = 'admin.health.view';
    case InvokeLLM = 'llm.invoke';
    case ExecuteTool = 'tool.execute';
    case ManageUsers = 'users.manage';

    public static function normalize(string|self $permission): string
    {
        return $permission instanceof self
            ? $permission->value
            : strtolower(trim($permission));
    }
}
