<?php

namespace App\Services\Topology;

use App\Models\Topology;
use App\Models\TopologyConfig;
use App\Models\TopologyDevice;
use App\Models\TopologyInterface;
use App\Models\TopologyLink;
use App\Models\TopologyValidationResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TopologyGenerationService
{
    /**
     * Standard interface configurations for different device types.
     */
    public const ROUTER_INTERFACES = [
        'GigabitEthernet0/0',
        'GigabitEthernet0/1',
        'GigabitEthernet0/2',
    ];

    public const SWITCH_INTERFACES = [
        'FastEthernet0/1',
        'FastEthernet0/2',
        'FastEthernet0/3',
        'FastEthernet0/4',
        'FastEthernet0/24',
    ];

    public const ENDPOINT_INTERFACES = [
        'FastEthernet0',
    ];

    public function __construct(
        private readonly AiTopologyParserService $parser,
        private readonly TopologyLayoutService $layoutService,
        private readonly VlanPlanGenerationService $vlanPlanService,
        private readonly IpPlanGenerationService $ipPlanService,
        private readonly RoutingPlanGenerationService $routingPlanService,
        private readonly CiscoTopologyConfigService $configService,
        private readonly TopologyValidationService $validationService,
        private readonly TopologySimulationGuideService $simulationGuideService
    ) {}

    public function generate(string $prompt, ?string $presetKey = null, array $expertOverrides = [], ?int $createdBy = null): array
    {
        $blueprint = array_replace_recursive($this->parser->parse($prompt, $presetKey), $expertOverrides);
        $vlans = $this->vlanPlanService->generate($blueprint);
        $ipPlan = $this->ipPlanService->generate($blueprint, $vlans);
        $routingPlan = $this->routingPlanService->generate($blueprint, $vlans, $ipPlan);
        $topologyDraft = $this->buildTopologyDraft($blueprint, $vlans, $ipPlan, $routingPlan);
        $manualDraft = ! empty($expertOverrides['devices']) && ! empty($expertOverrides['links']);
        if (! empty($expertOverrides['devices']) && ! empty($expertOverrides['links'])) {
            $topologyDraft = $this->normalizeExpertDraft($expertOverrides, $blueprint, $vlans, $routingPlan);
        }
        if (! $manualDraft) {
            $topologyDraft['devices'] = $this->layoutService->layout($topologyDraft['devices']);
        }

        // Ensure all devices have interfaces before validation
        $topologyDraft = $this->ensureDeviceInterfaces($topologyDraft);

        // Validate and auto-repair missing interfaces
        $validation = $this->validationService->validate($topologyDraft, $vlans, $ipPlan, $routingPlan, true);
        $configs = $this->configService->generate($topologyDraft, $vlans, $ipPlan, $routingPlan);
        $simulationSteps = $this->simulationGuideService->generate($topologyDraft, $vlans, $routingPlan);

        $topology = DB::transaction(function () use ($blueprint, $topologyDraft, $vlans, $ipPlan, $routingPlan, $validation, $configs, $simulationSteps, $createdBy): Topology {
            $topology = Topology::create([
                'name' => $blueprint['name'].' '.Str::upper(Str::random(4)),
                'slug' => Str::slug($blueprint['name'].'-'.Str::lower(Str::random(6))),
                'description' => $blueprint['description'],
                'scenario_type' => $blueprint['scenario_type'],
                'created_by' => $createdBy,
                'status' => 'generated',
                'default_routing_protocol' => $routingPlan['protocol'] ?? null,
                'metadata' => [
                    'prompt' => $blueprint['prompt'],
                    'blueprint' => $blueprint,
                    'vlans' => $vlans,
                    'ip_plan' => $ipPlan,
                    'routing_plan' => $routingPlan,
                    'simulation_steps' => $simulationSteps,
                ],
            ]);

            $deviceMap = [];
            foreach ($topologyDraft['devices'] as $deviceData) {

                $device = TopologyDevice::create([
                    'topology_id' => $topology->id,
                    'hostname' => $deviceData['name'],
                    'device_type' => $deviceData['type'],
                    'name' => $deviceData['name'],
                    'type' => $deviceData['type'],
                    'model' => $deviceData['model'],
                    'role' => $deviceData['role'],
                    'x_position' => $deviceData['x_position'],
                    'y_position' => $deviceData['y_position'],
                    'enable_secret' => 'class',
                    'console_password' => 'cisco',
                    'vty_password' => 'cisco',
                    'service_password_encryption' => true,
                    'routing_protocol' => $routingPlan['protocol'] ?? null,
                    'default_gateway' => $deviceData['default_gateway'] ?? null,
                    'vlans' => $deviceData['vlans'] ?? [],
                    'static_routes' => $routingPlan['static_routes'] ?? [],
                    'dhcp_pools' => $deviceData['dhcp_pools'] ?? [],
                    'nat_rules' => $deviceData['nat_rules'] ?? [],
                    'acl_rules' => $deviceData['acl_rules'] ?? [],
                    'ssh_settings' => $deviceData['ssh_settings'] ?? [],
                    'metadata' => $deviceData['metadata'] ?? [],
                ]);

                $deviceMap[$deviceData['name']] = $device;

                // Create interfaces for this device
                foreach ($deviceData['interfaces'] ?? [] as $interfaceData) {
                    TopologyInterface::create([
                        'topology_device_id' => $device->id,
                        'name' => $this->normalizeInterfaceName($interfaceData['name'] ?? ''),
                        'type' => $interfaceData['type'] ?? null,
                        'ip_address' => $interfaceData['ip_address'] ?? null,
                        'subnet_mask' => $interfaceData['subnet_mask'] ?? null,
                        'vlan_id' => $interfaceData['vlan_id'] ?? null,
                        'mode' => $interfaceData['mode'] ?? null,
                        'status' => $interfaceData['status'] ?? 'planned',
                        'metadata' => $interfaceData['metadata'] ?? [],
                    ]);
                }
            }

            // Create links - interfaces should now exist from the repair step
            foreach ($topologyDraft['links'] as $linkData) {
                TopologyLink::create([
                    'topology_id' => $topology->id,
                    'from_topology_device_id' => $deviceMap[$linkData['source_device']]->id,
                    'to_topology_device_id' => $deviceMap[$linkData['target_device']]->id,
                    'from_interface_name' => $linkData['source_interface'],
                    'to_interface_name' => $linkData['target_interface'],
                    'link_type' => $linkData['cable_type'] ?? 'copper-straight-through',
                    'vlan_id' => $linkData['vlan_id'] ?? null,
                    'allowed_vlans' => $linkData['allowed_vlans'] ?? null,
                    'source_device_id' => $deviceMap[$linkData['source_device']]->id,
                    'source_interface' => $linkData['source_interface'],
                    'target_device_id' => $deviceMap[$linkData['target_device']]->id,
                    'target_interface' => $linkData['target_interface'],
                    'cable_type' => $linkData['cable_type'] ?? 'copper-straight-through',
                    'status' => 'planned',
                    'metadata' => $linkData['metadata'] ?? [],
                ]);
            }

            foreach ($configs as $deviceName => $configText) {

                TopologyConfig::create([
                    'topology_id' => $topology->id,
                    'topology_device_id' => $deviceMap[$deviceName]->id,
                    'config_type' => 'cisco_cli',
                    'generated_cli' => $configText,
                    'validation_status' => empty($validation['errors']) ? 'valid' : 'warning',
                    'metadata' => [
                        'routing' => $routingPlan,
                        'vlans' => $vlans,
                    ],
                ]);
            }

            foreach ($validation['errors'] as $message) {

                TopologyValidationResult::create([
                    'topology_id' => $topology->id,
                    'severity' => 'error',
                    'category' => 'topology',
                    'message' => $message,
                    'suggested_fix' => 'Review the generated topology and adjust the offending device or link.',
                ]);
            }

            foreach ($validation['warnings'] as $message) {

                TopologyValidationResult::create([
                    'topology_id' => $topology->id,
                    'severity' => 'warning',
                    'category' => 'topology',
                    'message' => $message,
                    'suggested_fix' => 'Review the topology plan and confirm the item is intentional.',
                ]);
            }

            return $topology;
        });

        return [
            'topology' => $topology->load(['devices.topologyInterfaces', 'links.sourceDevice', 'links.targetDevice', 'configs.topologyDevice', 'validationResults']),
            'blueprint' => $blueprint,
            'vlans' => $vlans,
            'ip_plan' => $ipPlan,
            'routing_plan' => $routingPlan,
            'validation' => $validation,
            'configs' => $configs,
            'simulation_steps' => $simulationSteps,
        ];
    }

    /**
     * Ensure all devices have interfaces.
     * Creates standard interfaces for devices that are missing them.
     *
     * @param  array  $topology
     * @return array
     */
    public function ensureDeviceInterfaces(array $topology): array
    {
        $deviceInterfaceMap = $this->buildDeviceInterfaceMap($topology);
        $requiredInterfaces = $this->collectRequiredInterfaces($topology);

        foreach ($topology['devices'] as $index => &$device) {
            $deviceName = $device['name'] ?? '';

            // Ensure device has interfaces array
            if (! isset($device['interfaces'])) {
                $device['interfaces'] = [];
            }

            // Check which interfaces are required for this device
            $deviceRequired = $requiredInterfaces[$deviceName] ?? [];

            foreach ($deviceRequired as $requiredInterface) {
                $interfaceName = $requiredInterface['name'] ?? '';
                if ($interfaceName === '') {
                    continue;
                }

                // Check if interface already exists
                $exists = false;
                foreach ($device['interfaces'] as $existing) {
                    if (($existing['name'] ?? '') === $interfaceName) {
                        $exists = true;
                        break;
                    }
                }

                // Create interface if it doesn't exist
                if (! $exists) {
                    $device['interfaces'][] = $requiredInterface;
                }
            }
        }

        return $topology;
    }

    /**
     * Build a map of device names to their existing interfaces.
     *
     * @param  array  $topology
     * @return array<string, array<string, array>>
     */
    private function buildDeviceInterfaceMap(array $topology): array
    {
        $map = [];
        foreach ($topology['devices'] as $device) {
            $deviceName = $device['name'] ?? '';
            if ($deviceName === '') {
                continue;
            }
            $map[$deviceName] = [];
            foreach ($device['interfaces'] ?? [] as $interface) {
                $interfaceName = $interface['name'] ?? '';
                if ($interfaceName !== '') {
                    $map[$deviceName][$interfaceName] = $interface;
                }
            }
        }
        return $map;
    }

    /**
     * Collect all interfaces required by links from the topology.
     *
     * @param  array  $topology
     * @return array<string, array>
     */
    private function collectRequiredInterfaces(array $topology): array
    {
        $required = [];

        foreach ($topology['links'] ?? [] as $link) {
            $sourceDevice = $link['source_device'] ?? '';
            $sourceInterface = $link['source_interface'] ?? '';
            $targetDevice = $link['target_device'] ?? '';
            $targetInterface = $link['target_interface'] ?? '';

            // Add source interface requirement
            if ($sourceDevice !== '' && $sourceInterface !== '') {
                if (! isset($required[$sourceDevice])) {
                    $required[$sourceDevice] = [];
                }
                $required[$sourceDevice][] = $this->createInterfaceFromName($sourceDevice, $sourceInterface);
            }

            // Add target interface requirement
            if ($targetDevice !== '' && $targetInterface !== '') {
                if (! isset($required[$targetDevice])) {
                    $required[$targetDevice] = [];
                }
                $required[$targetDevice][] = $this->createInterfaceFromName($targetDevice, $targetInterface);
            }
        }

        return $required;
    }

    /**
     * Create an interface configuration from a normalized interface name.
     *
     * @param  string  $deviceName
     * @param  string  $interfaceName
     * @return array
     */
    public function createInterfaceFromName(string $deviceName, string $interfaceName): array
    {
        $normalizedName = $this->normalizeInterfaceName($interfaceName);
        $deviceType = $this->inferDeviceType($deviceName);

        // Determine interface type based on naming
        $isTrunk = str_contains(strtolower($interfaceName), '0/24') ||
                   str_contains(strtolower($interfaceName), '0/0');

        $interface = [
            'name' => $normalizedName,
            'type' => $isTrunk ? 'trunk' : 'access',
            'mode' => $isTrunk ? 'trunk' : 'access',
            'status' => 'planned',
        ];

        // Add specific properties based on interface type
        if (str_starts_with($normalizedName, 'GigabitEthernet')) {
            $interface['type'] = 'routed';
            $interface['mode'] = 'routed';
        } elseif (str_starts_with($normalizedName, 'FastEthernet')) {
            // FastEthernet is typically access by default
        } elseif (str_starts_with($normalizedName, 'Ethernet')) {
            $interface['type'] = 'access';
        }

        return $interface;
    }

    /**
     * Normalize interface name to standard format.
     *
     * @param  string  $interfaceName
     * @return string
     */
    public function normalizeInterfaceName(string $interfaceName): string
    {
        $interfaceName = trim($interfaceName);

        // Common Cisco interface prefixes
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

    /**
     * Infer device type from device name.
     *
     * @param  string  $deviceName
     * @return string
     */
    private function inferDeviceType(string $deviceName): string
    {
        $name = strtoupper($deviceName);

        if (str_starts_with($name, 'R')) {
            return 'router';
        }
        if (str_starts_with($name, 'SW')) {
            return 'switch';
        }
        if (str_starts_with($name, 'FW') || str_contains($name, 'ASA')) {
            return 'firewall';
        }
        if (str_starts_with($name, 'SRV') || str_contains($name, 'SERVER')) {
            return 'server';
        }
        if (str_starts_with($name, 'PC')) {
            return 'pc';
        }
        if (str_starts_with($name, 'INET') || str_contains($name, 'CLOUD')) {
            return 'cloud';
        }

        return 'unknown';
    }

    private function buildTopologyDraft(array $blueprint, array $vlans, array $ipPlan, array $routingPlan): array
    {
        $devices = [];
        $links = [];
        $routerCount = max(1, (int) ($blueprint['counts']['routers'] ?? 1));
        $switchCount = max(1, (int) ($blueprint['counts']['switches'] ?? 1));
        $pcCount = max(0, (int) ($blueprint['counts']['pcs'] ?? 0));
        $serverCount = max(0, (int) ($blueprint['counts']['servers'] ?? 0));
        $firewallCount = max(0, (int) ($blueprint['counts']['firewalls'] ?? 0));
        $cloudCount = max(0, (int) ($blueprint['counts']['clouds'] ?? 0));

        for ($i = 1; $i <= $routerCount; $i++) {
            $devices[] = $this->makeRouterDevice('R'.$i, $i, $vlans, $routingPlan, $blueprint);

        }

        for ($i = 1; $i <= $switchCount; $i++) {
            $devices[] = $this->makeSwitchDevice('SW'.$i, $i, $vlans, $blueprint);

        }

        for ($i = 1; $i <= $serverCount; $i++) {
            $devices[] = $this->makeEndpointDevice('SRV'.$i, 'server', $i, $vlans, $ipPlan, $blueprint);

        }

        for ($i = 1; $i <= $pcCount; $i++) {
            $devices[] = $this->makeEndpointDevice('PC'.$i, 'pc', $i, $vlans, $ipPlan, $blueprint);

        }

        for ($i = 1; $i <= $firewallCount; $i++) {
            $devices[] = $this->makeEndpointDevice('FW'.$i, 'firewall', $i, $vlans, $ipPlan, $blueprint);

        }

        for ($i = 1; $i <= $cloudCount; $i++) {
            $devices[] = $this->makeEndpointDevice('INET'.$i, 'cloud', $i, $vlans, $ipPlan, $blueprint);

        }

        $topologyType = $blueprint['scenario_type'];
        if ($topologyType === 'static_routing_lab' && $routerCount >= 2 && $switchCount >= 2) {
            $links = $this->buildStaticRoutingLinks($devices, $vlans);
        } elseif (in_array($topologyType, ['router_on_a_stick', 'vlan_lab', 'secure_enterprise_lab', 'aaa_security_lab'], true)) {
            $links = $this->buildVlanOrServicesLinks($devices, $vlans, $blueprint);
        } else {
            $links = $this->buildBasicLanLinks($devices, $vlans, $blueprint);
        }

        if (($blueprint['services']['nat'] ?? false) === true) {
            $links[] = [
                'source_device' => 'R1',
                'source_interface' => 'GigabitEthernet0/2',
                'target_device' => 'INET1',
                'target_interface' => 'Ethernet0',
                'cable_type' => 'copper-straight-through',
                'metadata' => ['internet' => true],
            ];
        }

        return [
            'name' => $blueprint['name'],
            'description' => $blueprint['description'],
            'scenario_type' => $blueprint['scenario_type'],
            'services' => $blueprint['services'],
            'security' => [
                'ssh_username' => 'admin',
                'ssh_domain' => 'autoconfiglab.local',
            ],
            'devices' => $devices,
            'links' => $links,
        ];
    }

    private function normalizeExpertDraft(array $expertOverrides, array $blueprint, array $vlans, array $routingPlan): array
    {
        $devices = array_map(function (array $device): array {
            return [
                'name' => $device['name'] ?? $device['hostname'] ?? 'Device',
                'type' => strtolower((string) ($device['type'] ?? $device['device_type'] ?? 'pc')),
                'model' => $device['model'] ?? null,
                'role' => $device['role'] ?? ($device['type'] ?? 'endpoint'),
                'x_position' => $device['x_position'] ?? 0,
                'y_position' => $device['y_position'] ?? 0,
                'vlans' => $device['vlans'] ?? [],
                'interfaces' => $device['interfaces'] ?? [],
                'default_gateway' => $device['default_gateway'] ?? null,
                'metadata' => $device['metadata'] ?? [],
                'dhcp_pools' => $device['dhcp_pools'] ?? [],
                'nat_rules' => $device['nat_rules'] ?? [],
                'acl_rules' => $device['acl_rules'] ?? [],
                'ssh_settings' => $device['ssh_settings'] ?? [],
            ];
        }, $expertOverrides['devices']);

        $links = array_map(function (array $link): array {
            return [
                'source_device' => $link['source_device'] ?? $link['from_device'] ?? '',
                'source_interface' => $link['source_interface'] ?? $link['from_interface'] ?? '',
                'target_device' => $link['target_device'] ?? $link['to_device'] ?? '',
                'target_interface' => $link['target_interface'] ?? $link['to_interface'] ?? '',
                'cable_type' => $link['cable_type'] ?? $link['link_type'] ?? 'copper-straight-through',
                'vlan_id' => $link['vlan_id'] ?? null,
                'allowed_vlans' => $link['allowed_vlans'] ?? null,
                'status' => $link['status'] ?? 'planned',
                'metadata' => $link['metadata'] ?? [],
            ];
        }, $expertOverrides['links']);

        return [
            'name' => $expertOverrides['topology']['name'] ?? $blueprint['name'],
            'description' => $expertOverrides['topology']['description'] ?? $blueprint['description'],
            'scenario_type' => $expertOverrides['topology']['scenario_type'] ?? $blueprint['scenario_type'],
            'services' => array_replace_recursive($blueprint['services'], $expertOverrides['services'] ?? []),
            'security' => array_replace_recursive([
                'ssh_username' => 'admin',
                'ssh_domain' => 'autoconfiglab.local',
            ], $expertOverrides['security'] ?? []),
            'devices' => $devices,
            'links' => $links,
        ];
    }

    private function makeRouterDevice(string $name, int $index, array $vlans, array $routingPlan, array $blueprint): array
    {
        $subinterfaces = [];
        if (count($vlans) > 1 || ($blueprint['scenario_type'] ?? '') === 'router_on_a_stick') {

            foreach ($vlans as $vlan) {

                $subinterfaces[] = [
                    'name' => 'GigabitEthernet0/0.'.$vlan['id'],
                    'vlan_id' => $vlan['id'],
                    'ip_address' => $vlan['gateway'],
                    'subnet_mask' => '255.255.255.0',

                    'ospf_md5' => (bool) ($routingPlan['ospf_md5'] ?? false),
                ];
            }
        }

        return [
            'name' => $name,
            'type' => 'router',
            'model' => 'ISR4331',
            'role' => $index === 1 ? 'edge' : 'core',
            'x_position' => null,
            'y_position' => null,
            'vlans' => $vlans,
            'interfaces' => array_filter([
                [
                    'name' => 'GigabitEthernet0/0',
                    'type' => count($subinterfaces) > 0 ? 'trunk' : 'routed',
                    'mode' => count($subinterfaces) > 0 ? 'trunk' : 'routed',
                    'ip_address' => count($subinterfaces) === 0 ? '10.0.0.1' : null,
                    'subnet_mask' => count($subinterfaces) === 0 ? '255.255.255.252' : null,
                    'allowed_vlans' => implode(',', array_map(fn ($vlan) => (string) $vlan['id'], $vlans)),
                    'subinterfaces' => $subinterfaces,
                ],
                $index > 1 ? [
                    'name' => 'GigabitEthernet0/1',
                    'type' => 'routed',
                    'mode' => 'routed',
                    'ip_address' => sprintf('10.0.%d.1', $index),
                    'subnet_mask' => '255.255.255.252',

                ] : null,
            ]),
            'default_gateway' => null,
            'metadata' => ['routing' => $routingPlan],
            'dhcp_pools' => [],
            'nat_rules' => [],
            'acl_rules' => [],
            'ssh_settings' => ['enabled' => true],
        ];
    }

    private function makeSwitchDevice(string $name, int $index, array $vlans, array $blueprint): array
    {
        $interfaces = [];
        for ($i = 1; $i <= 4; $i++) {

            $vlan = $vlans[($i - 1) % max(1, count($vlans))];
            $interfaces[] = [

                'name' => 'FastEthernet0/'.$i,
                'type' => 'access',
                'mode' => 'access',
                'vlan_id' => $vlan['id'],
            ];
        }
        $interfaces[] = [
            'name' => 'FastEthernet0/24',
            'type' => 'trunk',
            'mode' => 'trunk',
            'allowed_vlans' => implode(',', array_map(fn ($vlan) => (string) $vlan['id'], $vlans)),
        ];

        return [
            'name' => $name,
            'type' => 'switch',
            'model' => '2960',
            'role' => $index === 1 ? 'access' : 'distribution',
            'x_position' => null,
            'y_position' => null,
            'vlans' => $vlans,
            'interfaces' => $interfaces,
            'default_gateway' => '192.168.99.1',
            'metadata' => ['scenario' => $blueprint['scenario_type']],
            'dhcp_pools' => [],
            'nat_rules' => [],
            'acl_rules' => [],
            'ssh_settings' => ['enabled' => true],
        ];
    }

    private function makeEndpointDevice(string $name, string $type, int $index, array $vlans, array $ipPlan, array $blueprint): array
    {
        $vlan = $vlans[($index - 1) % max(1, count($vlans))];
        $subnetPrefix = explode('/', $vlan['subnet'])[0];

        $ipAddress = $this->assignIpByType($type, $subnetPrefix, $index, $vlan['id']);

        return [
            'name' => $name,
            'type' => $type,
            'model' => $type === 'server' ? 'Server-PT' : ($type === 'firewall' ? 'ASA5505' : ($type === 'cloud' ? 'Cloud-PT' : 'PC-PT')),
            'role' => $type,
            'x_position' => null,
            'y_position' => null,
            'vlans' => [$vlan],
            'interfaces' => [[
                'name' => 'FastEthernet0',
                'type' => 'access',
                'mode' => 'access',
                'ip_address' => $ipAddress,
                'subnet_mask' => '255.255.255.0',
                'vlan_id' => $vlan['id'],
                'status' => 'planned',
            ]],
            'gateway' => $vlan['gateway'],
            'default_gateway' => $vlan['gateway'],
            'metadata' => ['services' => $blueprint['services']],
            'dhcp_pools' => [],
            'nat_rules' => [],
            'acl_rules' => [],
            'ssh_settings' => ['enabled' => false],
        ];
    }

    private function assignIpByType(string $type, string $subnetPrefix, int $index, int $vlanId): string
    {

        if ($type === 'server') {
            $serverIndex = $index;
            if ($serverIndex >= 10) {

                $serverIndex = ($serverIndex % 10) + 10;
                if ($serverIndex > 49) {

                    $serverIndex = 10 + (($serverIndex - 10) % 40);

                }
            }
            return preg_replace('/\.0$/', '.' . $serverIndex, $subnetPrefix);

        }

        $pcIndex = 100 + $index;
        return preg_replace('/\.0$/', '.' . $pcIndex, $subnetPrefix);

    }

    private function buildBasicLanLinks(array $devices, array $vlans, array $blueprint): array
    {
        $links = [[
            'source_device' => 'R1',
            'source_interface' => 'GigabitEthernet0/0',
            'target_device' => 'SW1',
            'target_interface' => 'FastEthernet0/24',
            'cable_type' => 'copper-straight-through',
            'metadata' => ['role' => 'uplink'],
        ]];

        $hostIndex = 1;
        foreach ($devices as $device) {

            if (! in_array($device['type'], ['pc', 'server', 'firewall', 'cloud'], true)) {

                continue;
            }


            $links[] = [
                'source_device' => $device['name'],
                'source_interface' => 'FastEthernet0',
                'target_device' => 'SW1',
                'target_interface' => 'FastEthernet0/'.min(4, $hostIndex),
                'cable_type' => 'copper-straight-through',
                'metadata' => ['vlan' => $device['vlans'][0]['id'] ?? 10],
            ];
            $hostIndex++;
        }


        return $links;
    }

    private function buildVlanOrServicesLinks(array $devices, array $vlans, array $blueprint): array
    {
        $links = [[
            'source_device' => 'R1',
            'source_interface' => 'GigabitEthernet0/0',
            'target_device' => 'SW1',
            'target_interface' => 'FastEthernet0/24',
            'cable_type' => 'copper-straight-through',
            'metadata' => ['trunk' => true],
        ]];

        $hostPorts = [1, 2, 3, 4];
        foreach ($devices as $device) {

            if (! in_array($device['type'], ['pc', 'server', 'firewall'], true)) {

                continue;
            }


            $port = array_shift($hostPorts) ?: 4;
            $links[] = [
                'source_device' => $device['name'],
                'source_interface' => 'FastEthernet0',
                'target_device' => 'SW1',
                'target_interface' => 'FastEthernet0/'.$port,
                'cable_type' => 'copper-straight-through',
                'metadata' => ['vlan' => $device['vlans'][0]['id'] ?? 10],
            ];
        }


        return $links;
    }

    private function buildStaticRoutingLinks(array $devices, array $vlans): array
    {
        return [
            [
                'source_device' => 'R1',
                'source_interface' => 'GigabitEthernet0/1',
                'target_device' => 'R2',
                'target_interface' => 'GigabitEthernet0/1',
                'cable_type' => 'serial',
                'metadata' => ['p2p' => true],
            ],
            [
                'source_device' => 'R1',
                'source_interface' => 'GigabitEthernet0/0',
                'target_device' => 'SW1',
                'target_interface' => 'FastEthernet0/24',
                'cable_type' => 'copper-straight-through',
                'metadata' => ['lan' => true],
            ],
            [
                'source_device' => 'R2',
                'source_interface' => 'GigabitEthernet0/0',
                'target_device' => 'SW2',
                'target_interface' => 'FastEthernet0/24',
                'cable_type' => 'copper-straight-through',
                'metadata' => ['lan' => true],
            ],
        ];
    }
}