<?php

namespace Tests\Feature;

use App\Models\GeneratedConfig;
use App\Models\Topology;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopologyGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_topology_can_be_created_and_configs_generated_for_all_devices(): void
    {
        $devicesJson = json_encode([
            [
                'hostname' => 'R1',
                'device_type' => 'router',
                'routing_protocol' => 'ospf',
                'interfaces' => [
                    ['name' => 'GigabitEthernet0/0', 'mode' => 'routed', 'ip_address' => '192.168.50.1', 'subnet_mask' => '255.255.255.0'],
                ],
            ],
            [
                'hostname' => 'SW1',
                'device_type' => 'switch',
                'vlans' => [
                    ['id' => 10, 'name' => 'SALES'],
                ],
                'interfaces' => [
                    ['name' => 'FastEthernet0/1', 'mode' => 'access', 'vlan_id' => 10],
                    ['name' => 'FastEthernet0/24', 'mode' => 'trunk', 'allowed_vlans' => '10'],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES);

        $linksJson = json_encode([
            [
                'from_device' => 'R1',
                'from_interface' => 'GigabitEthernet0/0',
                'to_device' => 'SW1',
                'to_interface' => 'FastEthernet0/24',
                'link_type' => 'routed',
            ],
        ], JSON_UNESCAPED_SLASHES);

        $this->withSession(['role' => 'engineer'])
            ->post('/topologies', [
                'name' => 'Test Topology',
                'description' => 'Topology for generation test',
                'default_routing_protocol' => 'ospf',
                'devices_json' => $devicesJson,
                'links_json' => $linksJson,
            ])
            ->assertRedirect();

        $topology = Topology::query()->firstOrFail();
        $this->assertDatabaseHas('topologies', ['id' => $topology->id, 'name' => 'Test Topology']);
        $this->assertDatabaseCount('topology_devices', 2);
        $this->assertDatabaseCount('topology_links', 1);

        $this->withSession(['role' => 'engineer'])
            ->post('/topologies/'.$topology->id.'/generate-configs')
            ->assertRedirect(route('topologies.show', $topology));

        $this->assertDatabaseCount('generated_configs', 2);
        $r1Config = GeneratedConfig::query()
            ->whereHas('topologyDevice', fn ($query) => $query->where('hostname', 'R1'))
            ->firstOrFail();

        $this->assertStringContainsString('hostname R1', $r1Config->config_text);
        $this->assertStringContainsString('router ospf 1', $r1Config->config_text);
    }
}
