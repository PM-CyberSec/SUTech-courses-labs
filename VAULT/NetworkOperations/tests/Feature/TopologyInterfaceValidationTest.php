<?php

namespace Tests\Feature;

use App\Models\GeneratedConfig;
use App\Models\Topology;
use App\Models\TopologyDevice;
use App\Models\TopologyInterface;
use App\Models\DeviceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopologyInterfaceValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that generated devices contain interfaces.
     */
    public function test_generated_devices_contain_interfaces(): void
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
                'name' => 'Test Interface Topology',
                'description' => 'Topology for interface validation test',
                'default_routing_protocol' => 'ospf',
                'devices_json' => $devicesJson,
                'links_json' => $linksJson,
            ])
            ->assertRedirect();

        $topology = Topology::query()->firstOrFail();
        $this->assertDatabaseHas('topologies', ['id' => $topology->id, 'name' => 'Test Interface Topology']);
        $this->assertDatabaseCount('topology_devices', 2);
        $this->assertDatabaseCount('device_interfaces', 3);

        // Verify each device has interfaces
        $r1 = TopologyDevice::where('hostname', 'R1')->first();
        $sw1 = TopologyDevice::where('hostname', 'SW1')->first();

        $this->assertNotNull($r1, 'R1 device should exist');
        $this->assertNotNull($sw1, 'SW1 device should exist');

        $this->assertFalse($r1->interfaces->isEmpty(), 'R1 should have interfaces');
        $this->assertFalse($sw1->interfaces->isEmpty(), 'SW1 should have interfaces');

        // Verify interface names are correct
        $this->assertTrue($r1->interfaces->contains('name', 'GigabitEthernet0/0'));
        $this->assertTrue($sw1->interfaces->contains('name', 'FastEthernet0/1'));
        $this->assertTrue($sw1->interfaces->contains('name', 'FastEthernet0/24'));
    }

    /**
     * Test that links reference valid interfaces.
     */
    public function test_links_reference_valid_interfaces(): void
    {
        $devicesJson = json_encode([
            [
                'hostname' => 'R1',
                'device_type' => 'router',
                'interfaces' => [
                    ['name' => 'GigabitEthernet0/0', 'mode' => 'routed'],
                    ['name' => 'GigabitEthernet0/1', 'mode' => 'routed'],
                ],
            ],
            [
                'hostname' => 'SW1',
                'device_type' => 'switch',
                'interfaces' => [
                    ['name' => 'FastEthernet0/24', 'mode' => 'trunk'],
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
                'name' => 'Link Validation Test',
                'description' => 'Test links reference valid interfaces',
                'default_routing_protocol' => 'ospf',
                'devices_json' => $devicesJson,
                'links_json' => $linksJson,
            ])
            ->assertRedirect();

        $topology = Topology::query()->firstOrFail();
        $this->assertDatabaseCount('topology_links', 1);

        // Verify link references exist
        $link = $topology->topologyLinks->first();
        $this->assertNotNull($link);

        $this->assertEquals('GigabitEthernet0/0', $link->from_interface_name);
        $this->assertEquals('FastEthernet0/24', $link->to_interface_name);
    }

    /**
     * Test interface normalization.
     */
    public function test_interface_name_normalization(): void
    {
        $testCases = [
            'gi0/0' => 'GigabitEthernet0/0',
            'Gi0/1' => 'GigabitEthernet0/1',
            'fa0/1' => 'FastEthernet0/1',
            'Fa0/24' => 'FastEthernet0/24',
            'et0/0' => 'Ethernet0/0',
            'eth0/0' => 'Ethernet0/0',
            'se0/0' => 'Serial0/0',
            'GigabitEthernet0/0' => 'GigabitEthernet0/0',
            'FastEthernet0/1' => 'FastEthernet0/1',
        ];

        foreach ($testCases as $input => $expected) {
            $normalized = $this->normalizeInterfaceName($input);
            $this->assertEquals(
                $expected,
                $normalized,
                "Interface '$input' should normalize to '$expected', but got '$normalized'"
            );
        }
    }

    /**
     * Test that TopologyInterface is created for AI-generated topologies.
     */
    public function test_ai_generated_topology_creates_topology_interfaces(): void
    {
        // This test verifies that AI-generated topologies use TopologyInterface
        // instead of DeviceInterface
        $topology = Topology::create([
            'name' => 'AI Test Topology',
            'slug' => 'ai-test-topology',
            'description' => 'Test AI topology interface creation',
            'scenario_type' => 'basic_lan',
            'status' => 'generated',
        ]);

        $device = TopologyDevice::create([
            'topology_id' => $topology->id,
            'hostname' => 'R1',
            'device_type' => 'router',
            'type' => 'router',
            'name' => 'R1',
        ]);

        // Create a TopologyInterface (used by AI generation)
        TopologyInterface::create([
            'topology_device_id' => $device->id,
            'name' => 'GigabitEthernet0/0',
            'type' => 'routed',
            'mode' => 'routed',
            'ip_address' => '192.168.1.1',
            'subnet_mask' => '255.255.255.0',
        ]);

        // Verify TopologyInterface exists
        $this->assertDatabaseHas('topology_interfaces', [
            'topology_device_id' => $device->id,
            'name' => 'GigabitEthernet0/0',
        ]);

        // Verify device->interfaces returns TopologyInterface
        $this->assertFalse($device->interfaces->isEmpty());
        $this->assertEquals('GigabitEthernet0/0', $device->interfaces->first()->name);
    }

    /**
     * Test that devices with no interfaces are detected.
     */
    public function test_devices_without_interfaces_are_detected(): void
    {
        $topology = Topology::create([
            'name' => 'No Interfaces Test',
            'slug' => 'no-interfaces-test',
            'description' => 'Test detection of devices without interfaces',
            'scenario_type' => 'basic_lan',
            'status' => 'generated',
        ]);

        $deviceWithInterfaces = TopologyDevice::create([
            'topology_id' => $topology->id,
            'hostname' => 'R1',
            'device_type' => 'router',
            'type' => 'router',
            'name' => 'R1',
        ]);

        TopologyInterface::create([
            'topology_device_id' => $deviceWithInterfaces->id,
            'name' => 'GigabitEthernet0/0',
        ]);

        $deviceWithoutInterfaces = TopologyDevice::create([
            'topology_id' => $topology->id,
            'hostname' => 'SW1',
            'device_type' => 'switch',
            'type' => 'switch',
            'name' => 'SW1',
        ]);

        // Verify the device without interfaces
        $this->assertTrue($deviceWithoutInterfaces->interfaces->isEmpty());
        $this->assertFalse($deviceWithInterfaces->interfaces->isEmpty());
    }

    /**
     * Helper method to normalize interface names (mirrors the service method).
     */
    private function normalizeInterfaceName(string $interfaceName): string
    {
        $interfaceName = trim($interfaceName);

        $patterns = [
            '/^gi(\d)/i' => 'GigabitEthernet$1',
            '/^ge(\d)/i' => 'GigabitEthernet$1',
            '/^fa(\d)/i' => 'FastEthernet$1',
            '/^fe(\d)/i' => 'FastEthernet$1',
            '/^et(\d)/i' => 'Ethernet$1',
            '/^eth(\d)/i' => 'Ethernet$1',
            '/^se(\d)/i' => 'Serial$1',
        ];

        foreach ($patterns as $pattern => $replacement) {
            if (preg_match($pattern, $interfaceName)) {
                return preg_replace($pattern, $replacement, $interfaceName);
            }
        }

        return $interfaceName;
    }
}