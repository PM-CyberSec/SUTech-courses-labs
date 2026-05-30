<?php

namespace Database\Seeders;

use App\Models\Topology;
use App\Models\TopologyDevice;
use App\Models\TopologyLink;
use App\Services\Topology\TopologyConfigGeneratorService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TopologySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $topology = Topology::create([
            'name' => 'Campus Lab Topology',
            'slug' => Str::slug('Campus Lab Topology'),
            'description' => 'Example topology for router-switch-multilayer generation.',
            'default_routing_protocol' => 'ospf',
            'metadata' => ['seeded' => true],
        ]);

        $router = TopologyDevice::create([
            'topology_id' => $topology->id,
            'hostname' => 'R1',
            'device_type' => 'router',
            'enable_secret' => 'class',
            'console_password' => 'cisco',
            'vty_password' => 'cisco',
            'service_password_encryption' => true,
            'routing_protocol' => 'ospf',
            'static_routes' => [
                ['destination' => '172.16.10.0', 'mask' => '255.255.255.0', 'next_hop' => '10.0.0.2'],
            ],
            'dhcp_pools' => [
                [
                    'pool_name' => 'USERS_POOL',
                    'network' => '192.168.10.0',
                    'mask' => '255.255.255.0',
                    'default_router' => '192.168.10.1',
                    'dns_server' => '8.8.8.8',
                ],
            ],
            'nat_rules' => [
                'inside_interfaces' => ['GigabitEthernet0/0'],
                'outside_interfaces' => ['GigabitEthernet0/1'],
                'dynamic' => [
                    'acl' => 1,
                    'network' => '192.168.10.0',
                    'wildcard' => '0.0.0.255',
                    'overload_interface' => 'GigabitEthernet0/1',
                ],
                'static' => [
                    ['inside_local' => '192.168.10.10', 'inside_global' => '203.0.113.10'],
                ],
            ],
            'acl_rules' => [
                ['number' => 10, 'action' => 'permit', 'source' => '192.168.10.0 0.0.0.255'],
            ],
            'ssh_settings' => [
                'enabled' => true,
                'username' => 'admin',
                'password' => 'admin123',
                'domain' => 'autolab.local',
                'rsa_bits' => 1024,
            ],
            'metadata' => [
                'ospf' => [
                    'process_id' => 1,
                    'networks' => [
                        ['network' => '192.168.10.0', 'wildcard' => '0.0.0.255', 'area' => 0],
                        ['network' => '10.0.0.0', 'wildcard' => '0.0.0.3', 'area' => 0],
                    ],
                ],
            ],
        ]);

        $router->interfaces()->createMany([
            ['name' => 'GigabitEthernet0/0', 'mode' => 'routed', 'ip_address' => '192.168.10.1', 'subnet_mask' => '255.255.255.0'],
            ['name' => 'GigabitEthernet0/1', 'mode' => 'routed', 'ip_address' => '10.0.0.1', 'subnet_mask' => '255.255.255.252'],
        ]);

        $switch = TopologyDevice::create([
            'topology_id' => $topology->id,
            'hostname' => 'SW1',
            'device_type' => 'switch',
            'enable_secret' => 'class',
            'console_password' => 'cisco',
            'vty_password' => 'cisco',
            'service_password_encryption' => true,
            'default_gateway' => '192.168.10.1',
            'vlans' => [
                ['id' => 10, 'name' => 'SALES'],
                ['id' => 20, 'name' => 'HR'],
            ],
            'ssh_settings' => [
                'enabled' => true,
                'username' => 'switchadmin',
                'password' => 'switch123',
                'domain' => 'autolab.local',
            ],
        ]);

        $switch->interfaces()->createMany([
            ['name' => 'FastEthernet0/1', 'mode' => 'access', 'vlan_id' => 10],
            ['name' => 'FastEthernet0/2', 'mode' => 'access', 'vlan_id' => 20],
            ['name' => 'FastEthernet0/24', 'mode' => 'trunk', 'allowed_vlans' => '10,20'],
        ]);

        $mls = TopologyDevice::create([
            'topology_id' => $topology->id,
            'hostname' => 'MLS1',
            'device_type' => 'multilayer_switch',
            'enable_secret' => 'class',
            'console_password' => 'cisco',
            'vty_password' => 'cisco',
            'service_password_encryption' => true,
            'routing_protocol' => 'eigrp',
            'vlans' => [
                ['id' => 30, 'name' => 'ENG'],
            ],
            'metadata' => [
                'eigrp' => [
                    'asn' => 100,
                    'networks' => ['172.16.30.0 0.0.0.255', '10.0.0.4 0.0.0.3'],
                ],
            ],
        ]);

        $mls->interfaces()->createMany([
            ['name' => 'GigabitEthernet0/1', 'mode' => 'routed', 'ip_address' => '10.0.0.2', 'subnet_mask' => '255.255.255.252'],
            ['name' => 'GigabitEthernet0/2', 'mode' => 'trunk', 'allowed_vlans' => '30'],
            ['name' => 'Vlan30', 'mode' => 'layer3', 'ip_address' => '172.16.30.1', 'subnet_mask' => '255.255.255.0'],
        ]);

        TopologyLink::create([
            'topology_id' => $topology->id,
            'from_topology_device_id' => $router->id,
            'to_topology_device_id' => $mls->id,
            'from_interface_name' => 'GigabitEthernet0/1',
            'to_interface_name' => 'GigabitEthernet0/1',
            'link_type' => 'routed',
        ]);

        TopologyLink::create([
            'topology_id' => $topology->id,
            'from_topology_device_id' => $switch->id,
            'to_topology_device_id' => $mls->id,
            'from_interface_name' => 'FastEthernet0/24',
            'to_interface_name' => 'GigabitEthernet0/2',
            'link_type' => 'trunk',
            'allowed_vlans' => '10,20,30',
        ]);

        app(TopologyConfigGeneratorService::class)->generateForTopology($topology->fresh());
    }
}
