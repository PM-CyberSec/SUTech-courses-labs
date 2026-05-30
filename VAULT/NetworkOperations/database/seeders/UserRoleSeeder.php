<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserRoleSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Admin User', 'email' => 'admin@autoconfiglab.local', 'role' => 'admin'],
            ['name' => 'Engineer User', 'email' => 'engineer@autoconfiglab.local', 'role' => 'engineer'],
            ['name' => 'Viewer User', 'email' => 'viewer@autoconfiglab.local', 'role' => 'viewer'],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
