<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Inventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_can_read_devices_but_cannot_create_them(): void
    {
        $inventory = Inventory::create([
            'name' => 'Test Inventory',
            'group_name' => 'test_group',
            'is_active' => true,
        ]);

        Device::create([
            'inventory_id' => $inventory->id,
            'hostname' => 'edge-sw01',
            'mgmt_ip' => '10.10.10.10',
            'ansible_host' => '10.10.10.10',
            'ssh_port' => 22,
            'platform' => 'cisco.ios.ios',
            'vendor' => 'Cisco',
            'auth_username' => 'netops',
            'auth_password' => 'password',
            'become_password' => 'password',
            'connection' => 'network_cli',
            'status' => 'active',
        ]);

        $this->withHeaders(['X-Role' => 'viewer'])
            ->getJson('/api/v1/devices')
            ->assertOk()
            ->assertJsonPath('data.0.hostname', 'edge-sw01');

        $this->withHeaders(['X-Role' => 'viewer'])
            ->postJson('/api/v1/devices', [
                'inventory_id' => $inventory->id,
                'hostname' => 'edge-sw02',
                'mgmt_ip' => '10.10.10.11',
                'platform' => 'cisco.ios.ios',
                'auth_username' => 'netops',
            ])
            ->assertForbidden();
    }

    public function test_engineer_can_create_deployment_request(): void
    {
        $inventory = Inventory::create([
            'name' => 'Deployment Inventory',
            'group_name' => 'deployment_group',
            'is_active' => true,
        ]);

        $device = Device::create([
            'inventory_id' => $inventory->id,
            'hostname' => 'core-rtr01',
            'mgmt_ip' => '10.20.20.20',
            'ansible_host' => '10.20.20.20',
            'ssh_port' => 22,
            'platform' => 'cisco.ios.ios',
            'vendor' => 'Cisco',
            'auth_username' => 'netops',
            'auth_password' => 'password',
            'become_password' => 'password',
            'connection' => 'network_cli',
            'status' => 'active',
        ]);

        $this->withHeaders(['X-Role' => 'engineer'])
            ->postJson('/api/v1/deployments', [
                'device_id' => $device->id,
                'inventory_id' => $inventory->id,
                'playbook_name' => 'interface_config.yml',
                'variables' => ['interface_name' => 'GigabitEthernet0/2'],
                'execute_now' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('playbook_name', 'interface_config.yml');
    }

    public function test_engineer_can_create_ai_assisted_deployment_request(): void
    {
        $inventory = Inventory::create([
            'name' => 'AI Inventory',
            'group_name' => 'ai_group',
            'is_active' => true,
        ]);

        $device = Device::create([
            'inventory_id' => $inventory->id,
            'hostname' => 'branch-rtr01',
            'mgmt_ip' => '10.30.30.30',
            'ansible_host' => '10.30.30.30',
            'ssh_port' => 22,
            'platform' => 'cisco.ios.ios',
            'vendor' => 'Cisco',
            'auth_username' => 'netops',
            'auth_password' => 'password',
            'become_password' => 'password',
            'connection' => 'network_cli',
            'status' => 'active',
        ]);

        $this->withHeaders(['X-Role' => 'engineer'])
            ->postJson('/api/v1/deployments', [
                'device_id' => $device->id,
                'inventory_id' => $inventory->id,
                'intent_text' => 'Create VLAN 10 with DHCP and ACL',
                'preset_key' => 'small_office',
                'variables' => ['vlans' => [['id' => 10, 'name' => 'USERS']]],
                'execute_now' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('playbook_name', 'vlan_setup.yml');
    }
}
