<?php

namespace Tests\Feature;

use App\Models\ConfigTemplate;
use App\Models\Deployment;
use App\Models\Device;
use App\Models\Inventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_loads_from_root(): void
    {
        $this->get('/?as_role=viewer')
            ->assertOk()
            ->assertSee('AutoConfigLab');
    }

    public function test_devices_crud_pages_work(): void
    {
        $inventory = Inventory::create([
            'name' => 'INV-A',
            'group_name' => 'inv_a',
            'is_active' => true,
        ]);

        $this->withSession(['role' => 'engineer'])
            ->post('/devices', [
                'hostname' => 'dist-sw01',
                'ip_address' => '10.10.1.10',
                'platform' => 'cisco.ios.ios',
                'connection_type' => 'network_cli',
                'username' => 'netops',
                'password' => 'secret',
                'secret' => 'enable',
                'status' => 'active',
                'inventory_id' => $inventory->id,
            ])
            ->assertRedirect(route('devices.index'));

        $device = Device::query()->firstOrFail();

        $this->withSession(['role' => 'engineer'])
            ->put('/devices/'.$device->id, [
                'hostname' => 'dist-sw01-updated',
                'ip_address' => '10.10.1.11',
                'platform' => 'cisco.ios.ios',
                'connection_type' => 'network_cli',
                'username' => 'netops',
                'password' => 'secret',
                'secret' => 'enable',
                'status' => 'maintenance',
                'inventory_id' => $inventory->id,
            ])
            ->assertRedirect(route('devices.index'));

        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'hostname' => 'dist-sw01-updated',
            'mgmt_ip' => '10.10.1.11',
            'status' => 'maintenance',
        ]);

        $this->withSession(['role' => 'admin'])
            ->delete('/devices/'.$device->id)
            ->assertRedirect(route('devices.index'));

        $this->assertDatabaseMissing('devices', ['id' => $device->id]);
    }

    public function test_templates_crud_and_preview_work(): void
    {
        $device = $this->seedDevice();

        $this->withSession(['role' => 'engineer'])
            ->post('/templates', [
                'name' => 'Test Template',
                'category' => 'interface',
                'description' => 'Test',
                'template_body' => "interface {{ \$host['interface_name'] ?? 'Gi0/1' }}",
                'is_active' => 1,
            ])
            ->assertRedirect(route('templates.index'));

        $template = ConfigTemplate::query()->firstOrFail();

        $this->withSession(['role' => 'engineer'])
            ->post('/templates/'.$template->id.'/preview', [
                'device_id' => $device->id,
                'deployment_vars' => '{"sample":"value"}',
            ])
            ->assertOk()
            ->assertSee('Rendered Preview');

        $this->withSession(['role' => 'engineer'])
            ->put('/templates/'.$template->id, [
                'name' => 'Test Template Updated',
                'category' => 'routing',
                'description' => 'Updated',
                'template_body' => "ip route {{ \$host['destination_cidr'] ?? '1.1.1.0 255.255.255.0' }} 10.0.0.1",
                'is_active' => 1,
            ])
            ->assertRedirect(route('templates.index'));

        $this->assertDatabaseHas('config_templates', [
            'id' => $template->id,
            'name' => 'Test Template Updated',
            'category' => 'routing',
        ]);
    }

    public function test_deployment_wizard_and_ai_assistant_flow_work(): void
    {
        Process::fake([
            '*' => Process::result(
                output: 'PLAY RECAP *** changed=0 failed=0',
                errorOutput: '',
                exitCode: 0
            ),
        ]);

        $device = $this->seedDevice();
        $template = ConfigTemplate::create([
            'name' => 'Wizard Template',
            'slug' => Str::slug('Wizard Template'),
            'category' => 'interface',
            'template_body' => 'interface Gi0/1',
            'version' => 1,
            'is_active' => true,
        ]);

        $this->withSession(['role' => 'engineer'])
            ->get('/deployments/wizard')
            ->assertOk()
            ->assertSee('Deployment Wizard')
            ->assertSee('Device -> Goal -> Inputs -> Preview -> Deploy');

        $this->withSession(['role' => 'engineer'])
            ->post('/deployments', [
                'device_id' => $device->id,
                'inventory_id' => $device->inventory_id,
                'config_template_id' => $template->id,
                'goal' => 'access_segmentation',
                'preset_key' => 'small_office',
                'intent_text' => 'Create VLAN 10 with DHCP and ACL',
                'variables' => '{"vlans":[{"id":10,"name":"USERS"}]}',
                'execute_now' => 1,
                'simulation_mode' => 1,
            ])
            ->assertRedirect();

        $deployment = Deployment::query()->latest('id')->firstOrFail();
        $this->assertSame('success', $deployment->status);
        $this->assertNotEmpty($deployment->generated_config);
        $this->assertStringContainsString('Plan prepared for', (string) $deployment->notes);
    }

    public function test_deployment_execution_and_rollback_work(): void
    {
        Process::fake([
            '*' => Process::result(
                output: 'PLAY RECAP *** changed=0 failed=0',
                errorOutput: '',
                exitCode: 0
            ),
        ]);

        $device = $this->seedDevice();
        $template = ConfigTemplate::create([
            'name' => 'Deploy Template',
            'slug' => Str::slug('Deploy Template'),
            'category' => 'interface',
            'template_body' => 'interface Gi0/1',
            'version' => 1,
            'is_active' => true,
        ]);

        $this->withSession(['role' => 'engineer'])
            ->post('/deployments', [
                'device_id' => $device->id,
                'inventory_id' => $device->inventory_id,
                'config_template_id' => $template->id,
                'playbook_name' => 'interface_config.yml',
                'variables' => '{"interface_name":"GigabitEthernet0/2"}',
                'execute_now' => 1,
            ])
            ->assertRedirect();

        $deployment = Deployment::query()->latest('id')->firstOrFail();
        $this->assertDatabaseHas('deployments', [
            'id' => $deployment->id,
            'status' => 'success',
            'precheck_status' => 'passed',
            'postcheck_status' => 'passed',
        ]);

        $this->withSession(['role' => 'engineer'])
            ->post('/deployments/'.$deployment->id.'/rollback', [
                'playbook_name' => 'rollback.yml',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('rollbacks', [
            'deployment_id' => $deployment->id,
            'status' => 'success',
        ]);
    }

    public function test_rbac_restrictions_are_enforced(): void
    {
        $this->withSession(['role' => 'viewer'])
            ->get('/devices/create')
            ->assertForbidden();

        $this->withSession(['role' => 'viewer'])
            ->post('/deployments', [])
            ->assertForbidden();
    }

    private function seedDevice(): Device
    {
        $inventory = Inventory::create([
            'name' => 'Seed Inventory',
            'group_name' => 'seed_inventory',
            'is_active' => true,
        ]);

        return Device::create([
            'inventory_id' => $inventory->id,
            'hostname' => 'seed-sw01',
            'mgmt_ip' => '10.50.50.10',
            'ansible_host' => '10.50.50.10',
            'ssh_port' => 22,
            'platform' => 'cisco.ios.ios',
            'vendor' => 'Cisco',
            'auth_username' => 'netops',
            'auth_password' => 'password',
            'become_password' => 'secret',
            'connection' => 'network_cli',
            'status' => 'active',
        ]);
    }
}
