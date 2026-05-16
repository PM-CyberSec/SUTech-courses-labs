<?php

namespace App\Services\Network;

use App\Models\Topology;
use App\Models\TopologyDevice;
use App\Models\TopologyLink;
use App\Models\TopologyInterface;
use App\Models\GeneratedConfig;
use App\Services\Ansible\ValidationService;
use Illuminate\Support\Str;

class AiTopologyService
{
    private ValidationService $validationService;

    public function __construct()
    {
        $this->validationService = new ValidationService();
    }

    public function generateFromPrompt(string $prompt, array $options = []): Topology
    {
        $topologyData = $this->parsePromptToTopology($prompt, $options);

        $topology = Topology::create([
            'name' => $topologyData['name'],
            'slug' => Str::slug($topologyData['name']),
            'description' => $topologyData['description'],
            'default_routing_protocol' => $topologyData['routing_protocol'],
            'scenario_type' => $topologyData['scenario_type'],
            'status' => 'draft',
            'metadata' => [
                'prompt' => $prompt,
                'vlans' => $topologyData['vlans'],
                'ip_plan' => $topologyData['ip_plan'],
                'routing_plan' => $topologyData['routing_plan'],
                'validation' => $topologyData['validation'],
                'simulation_steps' => $topologyData['simulation_steps'],
            ],
        ]);

        foreach ($topologyData['devices'] as $deviceData) {
            $device = TopologyDevice::create([
                'topology_id' => $topology->id,
                'name' => $deviceData['name'],
                'device_type' => $deviceData['type'],
                'role' => $deviceData['role'],
                'x' => $deviceData['x'] ?? 100,
                'y' => $deviceData['y'] ?? 100,
                'metadata' => $deviceData['metadata'] ?? [],
            ]);

            foreach ($deviceData['interfaces'] ?? [] as $interfaceData) {
                TopologyInterface::create([
                    'topology_device_id' => $device->id,
                    'name' => $interfaceData['name'],
                    'ip_address' => $interfaceData['ip_address'] ?? null,
                    'subnet_mask' => $interfaceData['subnet_mask'] ?? null,
                    'gateway' => $interfaceData['gateway'] ?? null,
                    'vlan' => $interfaceData['vlan'] ?? null,
                    'interface_type' => $interfaceData['type'] ?? 'ethernet',
                    'status' => 'configured',
                ]);
            }
        }

        foreach ($topologyData['links'] as $linkData) {
            TopologyLink::create([
                'topology_id' => $topology->id,
                'source_device' => $linkData['source'],
                'source_interface' => $linkData['source_interface'],
                'target_device' => $linkData['target'],
                'target_interface' => $linkData['target_interface'],
                'link_type' => $linkData['type'] ?? 'ethernet',
                'cable_type' => $linkData['cable'] ?? 'straight',
            ]);
        }

        $this->generateConfigsForTopology($topology);

        $validationResult = $this->validationService->validateTopology($topology);
        $topology->update(['metadata->validation_result' => $validationResult]);

        return $topology;
    }

    private function parsePromptToTopology(string $prompt, array $options): array
    {
        $prompt = strtolower($prompt);

        $scenarioType = $this->detectScenarioType($prompt);
        $deviceConfigs = $this->generateDeviceConfigs($prompt, $scenarioType, $options);
        $vlans = $this->generateVlanPlan($prompt, $deviceConfigs);
        $ipPlan = $this->generateIpPlan($prompt, $deviceConfigs);
        $routingPlan = $this->generateRoutingPlan($prompt, $deviceConfigs);
        $links = $this->generateLinks($deviceConfigs);
        $validation = $this->generateValidationPlan($deviceConfigs);
        $simulationSteps = $this->generateSimulationSteps($deviceConfigs);

        $deviceCount = count($deviceConfigs);
        $name = $options['name'] ?? "AI Generated Lab {$deviceCount} Devices";

        return [
            'name' => $name,
            'description' => "Auto-generated topology from prompt: {$prompt}",
            'scenario_type' => $scenarioType,
            'routing_protocol' => $this->detectRoutingProtocol($prompt),
            'devices' => $deviceConfigs,
            'vlans' => $vlans,
            'ip_plan' => $ipPlan,
            'routing_plan' => $routingPlan,
            'links' => $links,
            'validation' => $validation,
            'simulation_steps' => $simulationSteps,
        ];
    }

    private function detectScenarioType(string $prompt): string
    {
        if (str_contains($prompt, 'router') && str_contains($prompt, 'stick')) {
            return 'router-on-stick';
        }
        if (str_contains($prompt, 'vlan') || str_contains($prompt, 'switch')) {
            return 'vlan-lab';
        }
        if (str_contains($prompt, 'routing') || str_contains($prompt, 'ospf') || str_contains($prompt, 'eigrp')) {
            return 'routing-lab';
        }
        if (str_contains($prompt, 'dhcp') || str_contains($prompt, 'pool')) {
            return 'dhcp-lab';
        }
        if (str_contains($prompt, 'wan') || str_contains($prompt, 'site')) {
            return 'wan-lab';
        }

        return 'basic-lab';
    }

    private function generateDeviceConfigs(string $prompt, string $scenarioType, array $options): array
    {
        $devices = [];

        if (str_contains($prompt, 'router') || $scenarioType === 'router-on-stick' || $scenarioType === 'routing-lab' || $scenarioType === 'wan-lab') {
            $devices[] = [
                'name' => 'Router-Core',
                'type' => 'router',
                'role' => 'core',
                'x' => 400,
                'y' => 150,
                'metadata' => [
                    'model' => 'ISR 4331',
                    'hostname' => 'R1',
                ],
                'interfaces' => [
                    ['name' => 'GigabitEthernet0/0/0', 'ip_address' => '192.168.1.1', 'subnet_mask' => '255.255.255.0', 'gateway' => null, 'vlan' => null, 'type' => 'routed'],
                    ['name' => 'GigabitEthernet0/0/1', 'ip_address' => '10.0.0.1', 'subnet_mask' => '255.255.255.252', 'gateway' => null, 'vlan' => null, 'type' => 'routed'],
                    ['name' => 'GigabitEthernet0/0/2', 'ip_address' => null, 'subnet_mask' => null, 'gateway' => null, 'vlan' => 10, 'type' => 'trunk'],
                ],
            ];
        }

        if (str_contains($prompt, 'switch') || $scenarioType === 'vlan-lab' || $scenarioType === 'router-on-stick') {
            $devices[] = [
                'name' => 'Switch-Dist',
                'type' => 'switch',
                'role' => 'distribution',
                'x' => 200,
                'y' => 300,
                'metadata' => [
                    'model' => 'Catalyst 2960-X',
                    'hostname' => 'SW1',
                ],
                'interfaces' => [
                    ['name' => 'FastEthernet0/1', 'ip_address' => null, 'subnet_mask' => null, 'gateway' => null, 'vlan' => 10, 'type' => 'access'],
                    ['name' => 'FastEthernet0/2', 'ip_address' => null, 'subnet_mask' => null, 'gateway' => null, 'vlan' => 20, 'type' => 'access'],
                    ['name' => 'GigabitEthernet0/1', 'ip_address' => null, 'subnet_mask' => null, 'gateway' => null, 'vlan' => null, 'type' => 'trunk'],
                ],
            ];

            if (str_contains($prompt, 'access') || $options['include_access'] ?? false) {
                $devices[] = [
                    'name' => 'Switch-Access-1',
                    'type' => 'switch',
                    'role' => 'access',
                    'x' => 100,
                    'y' => 400,
                    'metadata' => [
                        'model' => 'Catalyst 2960-X',
                        'hostname' => 'SW2',
                    ],
                    'interfaces' => [
                        ['name' => 'FastEthernet0/1', 'ip_address' => null, 'subnet_mask' => null, 'gateway' => null, 'vlan' => 10, 'type' => 'access'],
                        ['name' => 'FastEthernet0/2', 'ip_address' => null, 'subnet_mask' => null, 'gateway' => null, 'vlan' => 20, 'type' => 'access'],
                        ['name' => 'GigabitEthernet0/1', 'ip_address' => null, 'subnet_mask' => null, 'gateway' => null, 'vlan' => null, 'type' => 'trunk'],
                    ],
                ];
            }
        }

        if (str_contains($prompt, 'pc') || str_contains($prompt, 'host') || $options['include_hosts'] ?? true) {
            $devices[] = [
                'name' => 'PC-1',
                'type' => 'pc',
                'role' => 'endpoint',
                'x' => 50,
                'y' => 450,
                'metadata' => [
                    'os' => 'Windows',
                    'hostname' => 'PC1',
                ],
                'interfaces' => [
                    ['name' => 'Ethernet0', 'ip_address' => '192.168.10.100', 'subnet_mask' => '255.255.255.0', 'gateway' => '192.168.10.1', 'vlan' => 10, 'type' => 'access'],
                ],
            ];

            $devices[] = [
                'name' => 'PC-2',
                'type' => 'pc',
                'role' => 'endpoint',
                'x' => 150,
                'y' => 450,
                'metadata' => [
                    'os' => 'Windows',
                    'hostname' => 'PC2',
                ],
                'interfaces' => [
                    ['name' => 'Ethernet0', 'ip_address' => '192.168.20.100', 'subnet_mask' => '255.255.255.0', 'gateway' => '192.168.20.1', 'vlan' => 20, 'type' => 'access'],
                ],
            ];
        }

        if (empty($devices)) {
            $devices[] = [
                'name' => 'Router-1',
                'type' => 'router',
                'role' => 'core',
                'x' => 400,
                'y' => 200,
                'metadata' => [
                    'model' => 'ISR 4331',
                    'hostname' => 'R1',
                ],
                'interfaces' => [
                    ['name' => 'GigabitEthernet0/0', 'ip_address' => '192.168.1.1', 'subnet_mask' => '255.255.255.0', 'gateway' => null, 'vlan' => null],
                ],
            ];
        }

        return $devices;
    }

    private function generateVlanPlan(string $prompt, array $devices): array
    {
        $vlans = [];

        if (str_contains($prompt, 'vlan 10') || str_contains($prompt, 'vlan 20')) {
            $vlans[] = ['id' => 10, 'name' => 'DATA', 'description' => 'Data VLAN'];
            $vlans[] = ['id' => 20, 'name' => 'VOICE', 'description' => 'Voice VLAN'];
        } else {
            $vlans[] = ['id' => 10, 'name' => 'DEFAULT', 'description' => 'Default VLAN'];
        }

        if (str_contains($prompt, 'management') || str_contains($prompt, 'mgmt')) {
            $vlans[] = ['id' => 99, 'name' => 'MANAGEMENT', 'description' => 'Management VLAN'];
        }

        return $vlans;
    }

    private function generateIpPlan(string $prompt, array $devices): array
    {
        $ipPlan = [];

        if (str_contains($prompt, '192.168')) {
            $ipPlan[] = ['network' => '192.168.10.0/24', 'gateway' => '192.168.10.1', 'vlan' => 10, 'dhcp' => true, 'dhcp_start' => '100', 'dhcp_end' => '200'];
            $ipPlan[] = ['network' => '192.168.20.0/24', 'gateway' => '192.168.20.1', 'vlan' => 20, 'dhcp' => true, 'dhcp_start' => '100', 'dhcp_end' => '200'];
        } elseif (str_contains($prompt, '10.')) {
            $ipPlan[] = ['network' => '10.1.1.0/24', 'gateway' => '10.1.1.1', 'vlan' => 10, 'dhcp' => true, 'dhcp_start' => '100', 'dhcp_end' => '200'];
        } else {
            $ipPlan[] = ['network' => '192.168.1.0/24', 'gateway' => '192.168.1.1', 'vlan' => 1, 'dhcp' => true, 'dhcp_start' => '100', 'dhcp_end' => '200'];
        }

        return $ipPlan;
    }

    private function generateRoutingPlan(string $prompt, array $devices): array
    {
        $routingPlan = [];
        $protocol = $this->detectRoutingProtocol($prompt);

        if ($protocol !== 'none') {
            $routingPlan[] = [
                'protocol' => $protocol,
                'process_id' => 1,
                'area' => 0,
                'networks' => [
                    ['network' => '192.168.10.0', 'wildcard' => '0.0.0.255'],
                    ['network' => '192.168.20.0', 'wildcard' => '0.0.0.255'],
                    ['network' => '10.0.0.0', 'wildcard' => '0.0.0.3'],
                ],
            ];
        }

        return $routingPlan;
    }

    private function detectRoutingProtocol(string $prompt): string
    {
        if (str_contains($prompt, 'ospf')) {
            return 'ospf';
        }
        if (str_contains($prompt, 'eigrp')) {
            return 'eigrp';
        }
        if (str_contains($prompt, 'bgp')) {
            return 'bgp';
        }
        if (str_contains($prompt, 'static')) {
            return 'static';
        }

        return 'ospf';
    }

    private function generateLinks(array $devices): array
    {
        $links = [];

        $deviceTypes = array_column($devices, 'type');
        $deviceNames = array_column($devices, 'name');

        if (in_array('router', $deviceTypes) && in_array('switch', $deviceTypes)) {
            $routerIndex = array_search('router', $deviceTypes);
            $switchIndex = array_search('switch', $deviceTypes);

            $links[] = [
                'source' => $deviceNames[$routerIndex],
                'source_interface' => 'GigabitEthernet0/0/2',
                'target' => $deviceNames[$switchIndex],
                'target_interface' => 'GigabitEthernet0/1',
                'type' => 'ethernet',
                'cable' => 'straight',
            ];
        }

        $switchIndices = array_keys(array_filter($deviceTypes, fn($t) => $t === 'switch'));
        if (count($switchIndices) >= 2) {
            $links[] = [
                'source' => $deviceNames[$switchIndices[0]],
                'source_interface' => 'GigabitEthernet0/1',
                'target' => $deviceNames[$switchIndices[1]],
                'target_interface' => 'GigabitEthernet0/1',
                'type' => 'ethernet',
                'cable' => 'straight',
            ];
        }

        return $links;
    }

    private function generateValidationPlan(array $devices): array
    {
        $validation = [];

        foreach ($devices as $device) {
            $deviceValidation = [
                'device' => $device['name'],
                'checks' => [],
            ];

            $deviceValidation['checks'][] = ['type' => 'ping', 'target' => 'gateway', 'expected' => 'success'];
            $deviceValidation['checks'][] = ['type' => 'vlan', 'check' => 'show vlan', 'expected' => 'present'];

            if ($device['type'] === 'router') {
                $deviceValidation['checks'][] = ['type' => 'routing', 'check' => 'show ip route', 'expected' => 'connected'];
            }

            $validation[] = $deviceValidation;
        }

        return $validation;
    }

    private function generateSimulationSteps(array $devices): array
    {
        $steps = [];

        $steps[] = ['step' => 1, 'action' => 'Verify device connectivity', 'command' => 'ping', 'expected' => 'success'];
        $steps[] = ['step' => 2, 'action' => 'Verify VLAN configuration', 'command' => 'show vlan', 'expected' => 'vlan_present'];
        $steps[] = ['step' => 3, 'action' => 'Verify trunk links', 'command' => 'show interfaces trunk', 'expected' => 'trunk_active'];
        $steps[] = ['step' => 4, 'action' => 'Verify routing', 'command' => 'show ip route', 'expected' => 'routes_present'];

        return $steps;
    }

    public function generateConfigsForTopology(Topology $topology): void
    {
        $devices = $topology->topologyDevices()->with('interfaces')->get();

        foreach ($devices as $device) {
            $this->generateDeviceConfig($topology, $device);
        }
    }

    private function generateDeviceConfig(Topology $topology, TopologyDevice $device): GeneratedConfig
    {
        $config = [];
        $hostname = $device->metadata['hostname'] ?? $device->name;

        $config[] = '!';
        $config[] = '! Configuration generated by AutoConfigLab';
        $config[] = '! Topology: ' . $topology->name;
        $config[] = '! Device: ' . $device->name;
        $config[] = '! Generated: ' . now()->toIso8601String();
        $config[] = '!';

        $config[] = 'hostname ' . $hostname;

        if ($device->device_type === 'router') {
            $interfaces = $device->interfaces;
            foreach ($interfaces as $interface) {
                if ($interface->ip_address) {
                    $config[] = 'interface ' . $interface->name;
                    $config[] = ' ip address ' . $interface->ip_address . ' ' . ($interface->subnet_mask ?? '255.255.255.0');
                    $config[] = ' no shutdown';
                }
            }

            $routingProtocol = $topology->default_routing_protocol;
            if ($routingProtocol === 'ospf') {
                $config[] = 'router ospf 1';
                $config[] = ' network 0.0.0.0 255.255.255.255 area 0';
            } elseif ($routingProtocol === 'eigrp') {
                $config[] = 'router eigrp 1';
                $config[] = ' network 0.0.0.0';
            }
        }

        if ($device->device_type === 'switch') {
            $config[] = 'vtp mode transparent';

            $interfaces = $device->interfaces;
            foreach ($interfaces as $interface) {
                $config[] = 'interface ' . $interface->name;

                if ($interface->vlan && $interface->interface_type !== 'trunk') {
                    $config[] = ' switchport mode access';
                    $config[] = ' switchport access vlan ' . $interface->vlan;
                } else {
                    $config[] = ' switchport mode trunk';
                }

                $config[] = ' no shutdown';
            }
        }

        if ($device->device_type === 'pc') {
            $interfaces = $device->interfaces;
            foreach ($interfaces as $interface) {
                if ($interface->ip_address) {
                    $config[] = 'ip address ' . $interface->ip_address . ' ' . ($interface->subnet_mask ?? '255.255.255.0');
                    $config[] = 'ip default-gateway ' . $interface->gateway;
                }
            }
        }

        $config[] = '!';
        $config[] = 'end';

        $configContent = implode("\n", $config);

        return GeneratedConfig::create([
            'topology_id' => $topology->id,
            'device_id' => null,
            'device_name' => $device->name,
            'device_type' => $device->device_type,
            'config_content' => $configContent,
            'status' => 'generated',
        ]);
    }

    public function exportToJson(Topology $topology): array
    {
        return [
            'name' => $topology->name,
            'description' => $topology->description,
            'scenario_type' => $topology->scenario_type,
            'routing_protocol' => $topology->default_routing_protocol,
            'created_at' => $topology->created_at->toIso8601String(),
            'metadata' => $topology->metadata,
            'devices' => $topology->topologyDevices()->with('interfaces')->get()->toArray(),
            'links' => $topology->topologyLinks()->get()->toArray(),
        ];
    }
}