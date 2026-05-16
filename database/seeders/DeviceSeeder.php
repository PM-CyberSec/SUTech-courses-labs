<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\HostVariable;
use App\Models\Inventory;
use Illuminate\Database\Seeder;

class DeviceSeeder extends Seeder
{
    public function run(): void
    {
        $coreInventory = Inventory::query()->where('name', 'Core Switches')->first();
        $branchInventory = Inventory::query()->where('name', 'Branch Routers')->first();

        $devices = [
            [
                'hostname' => 'core-sw01',
                'mgmt_ip' => '10.0.0.11',
                'platform' => 'cisco.ios.ios',
                'inventory_id' => $coreInventory?->id,
            ],
            [
                'hostname' => 'branch-rtr01',
                'mgmt_ip' => '10.0.1.11',
                'platform' => 'cisco.ios.ios',
                'inventory_id' => $branchInventory?->id,
            ],
        ];

        foreach ($devices as $item) {
            $device = Device::query()->updateOrCreate(
                ['mgmt_ip' => $item['mgmt_ip']],
                [
                    'inventory_id' => $item['inventory_id'],
                    'hostname' => $item['hostname'],
                    'ansible_host' => $item['mgmt_ip'],
                    'ssh_port' => 22,
                    'platform' => $item['platform'],
                    'vendor' => 'Cisco',
                    'auth_username' => 'netops',
                    'auth_password' => 'ChangeMe123!',
                    'become_password' => 'ChangeMe123!',
                    'connection' => 'network_cli',
                    'status' => 'active',
                ]
            );

            $defaults = [
                'interface_name' => 'GigabitEthernet0/1',
                'interface_description' => 'AutoConfigLab managed port',
                'access_vlan' => '20',
                'vlan_id' => '20',
                'vlan_name' => 'USERS',
                'destination_cidr' => '172.16.10.0 255.255.255.0',
                'next_hop' => '10.0.0.1',
            ];

            foreach ($defaults as $key => $value) {
                HostVariable::query()->updateOrCreate(
                    ['device_id' => $device->id, 'key' => $key],
                    ['value' => (string) $value, 'is_secret' => false]
                );
            }
        }
    }
}
