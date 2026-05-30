<?php

namespace Database\Seeders;

use App\Models\Inventory;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        Inventory::query()->updateOrCreate(
            ['name' => 'Core Switches'],
            [
                'group_name' => 'core_switches',
                'description' => 'Core layer network devices.',
                'variables' => [
                    'ansible_connection' => 'network_cli',
                    'ansible_become' => true,
                ],
                'is_active' => true,
            ]
        );

        Inventory::query()->updateOrCreate(
            ['name' => 'Branch Routers'],
            [
                'group_name' => 'branch_routers',
                'description' => 'Edge routers in branch sites.',
                'variables' => [
                    'ansible_connection' => 'network_cli',
                    'ansible_become' => true,
                ],
                'is_active' => true,
            ]
        );
    }
}
