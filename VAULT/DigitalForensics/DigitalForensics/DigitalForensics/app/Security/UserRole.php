<?php

declare(strict_types=1);

namespace App\Security;

enum UserRole: string
{
    case Admin = 'admin';
    case Analyst = 'analyst';
    case Viewer = 'viewer';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::cases(),
        );
    }
}
