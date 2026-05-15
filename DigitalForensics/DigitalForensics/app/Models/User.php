<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Security\Permission;
use App\Security\PermissionRegistry;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'approved_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'approved_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function isApproved(): bool
    {
        return (bool) $this->is_active && $this->approved_at !== null;
    }

    public function hasRole(string ...$roles): bool
    {
        $normalizedRoles = array_map(
            static fn (string $role): string => strtolower(trim($role)),
            $roles,
        );

        return in_array(strtolower((string) $this->role), $normalizedRoles, true);
    }

    public function hasPermission(string|Permission $permission): bool
    {
        return app(PermissionRegistry::class)->allows($this, $permission);
    }
}
