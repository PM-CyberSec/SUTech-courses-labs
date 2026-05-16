<?php

namespace App\Services\Smart;

use App\Models\Device;
use App\Models\Inventory;
use App\Models\ConfigTemplate;
use Illuminate\Support\Str;

class AIAssistantService
{
    private const PRESET_MAP = [
        'small_office' => [
            'playbook' => 'vlan_setup.yml',
            'goal' => 'small_office',
            'payload' => [
                'vlan_id' => 10,
                'vlan_name' => 'OFFICE',
                'interface_name' => 'GigabitEthernet0/1',
            ],
            'recommendations' => [
                'Use 192.168.10.0/24 for the office network',
                'Enable DHCP with range 100-200',
                'Configure port security on access ports',
            ],
        ],
        'enterprise' => [
            'playbook' => 'deployment.yml',
            'goal' => 'enterprise',
            'payload' => [
                'vlan_id' => 100,
                'vlan_name' => 'ENTERPRISE',
                'routing_protocol' => 'ospf',
            ],
            'recommendations' => [
                'Use OSPF area 0 for core routing',
                'Configure VTP transparent mode',
                'Enable MSTP for spanning tree',
            ],
        ],
        'lab' => [
            'playbook' => 'vlan_setup.yml',
            'goal' => 'lab',
            'payload' => [
                'vlan_id' => 20,
                'vlan_name' => 'LAB',
                'interface_name' => 'FastEthernet0/1',
            ],
            'recommendations' => [
                'Use 192.168.20.0/24 for lab network',
                'Enable inter-VLAN routing on router',
                'Configure DHCP for lab devices',
            ],
        ],
        'datacenter' => [
            'playbook' => 'deployment.yml',
            'goal' => 'datacenter',
            'payload' => [
                'routing_protocol' => 'bgp',
                'bgp_asn' => 65001,
            ],
            'recommendations' => [
                'Use BGP for datacenter interconnect',
                'Configure redundant uplinks',
                'Enable VLAN trunking between switches',
            ],
        ],
    ];

    private const SCENARIO_MAP = [
        'success' => ['status' => 'completed', 'fail_reason' => null],
        'validation_failure' => ['status' => 'validation_failed', 'fail_reason' => 'Validation failed'],
        'connection_failure' => ['status' => 'failed', 'fail_reason' => 'Connection timeout'],
        'config_failure' => ['status' => 'failed', 'fail_reason' => 'Configuration error'],
    ];

    public function buildPlan(Device $device, ?Inventory $inventory, array $input): array
    {
        $presetKey = $input['preset_key'] ?? '';
        $scenarioKey = $input['scenario_key'] ?? '';
        $intentText = $input['intent_text'] ?? '';
        $goal = $input['goal'] ?? '';

        if ($presetKey && isset(self::PRESET_MAP[$presetKey])) {
            return $this->buildFromPreset($presetKey, $input);
        }

        if ($intentText) {
            return $this->buildFromIntent($intentText, $device, $input);
        }

        return $this->buildDefaultPlan($input);
    }

    private function buildFromPreset(string $presetKey, array $input): array
    {
        $preset = self::PRESET_MAP[$presetKey];
        $goal = $input['goal'] ?? $preset['goal'];

        $playbook = $input['playbook_name'] ?? $this->selectPlaybookFromGoal($goal);
        if (empty($playbook)) {
            $playbook = $preset['playbook'];
        }
        if (empty($playbook)) {
            $playbook = 'deployment.yml';
        }

        return [
            'playbook_name' => $playbook,
            'goal' => $goal,
            'preset_key' => $presetKey,
            'payload' => array_merge($preset['payload'], $input['payload'] ?? []),
            'recommendations' => $preset['recommendations'],
            'warnings' => [],
            'summary' => "Applied preset: {$presetKey}",
        ];
    }

private function buildFromIntent(string $intentText, Device $device, array $input): array
    {
        $intentLower = strtolower($intentText);
        $goal = $input['goal'] ?? '';

        $playbook = $this->selectPlaybookFromGoal($goal);
        $payload = [];
        $recommendations = [];
        $warnings = [];

        $defaultInterface = $this->getDefaultInterfaceForDevice($device);
        $hasVlanOrDhcpOrAcl = str_contains($intentLower, 'vlan')
            || str_contains($intentLower, 'dhcp')
            || str_contains($intentLower, 'acl')
            || str_contains($intentLower, 'interface')
            || str_contains($goal, 'vlan')
            || str_contains($goal, 'layer2');

        if ($hasVlanOrDhcpOrAcl) {
            if (str_contains($intentLower, 'vlan')) {
                $payload['vlan_id'] = $this->extractVlanId($intentText);
                $payload['vlan_name'] = $this->extractVlanName($intentText) ?? 'AUTO_VLAN';
                $recommendations[] = 'Verify VLAN ID is available';
            }

            if (str_contains($intentLower, 'dhcp')) {
                $recommendations[] = 'Configure DHCP pool for the network';
                if (! isset($payload['dhcp_pool'])) {
                    $vlanId = $payload['vlan_id'] ?? 10;
                    $payload['dhcp_pool'] = 'DHCP_POOL';
                    $payload['dhcp_network'] = "192.168.{$vlanId}.0";
                    $payload['dhcp_mask'] = '255.255.255.0';
                    $payload['dhcp_default_router'] = "192.168.{$vlanId}.1";
                }
            }

            if (str_contains($intentLower, 'acl')) {
                $recommendations[] = 'Configure ACL rules';
                $payload['acl_rules'] = [
                    ['action' => 'permit', 'protocol' => 'ip', 'source' => 'any', 'destination' => 'any'],
                ];
                $payload['acl_number'] = $payload['vlan_id'] ?? 100;
            }

            $payload['interface_name'] = $defaultInterface;
        }

        if (str_contains($intentLower, 'routing') || str_contains($goal, 'routing')) {
            $payload['routing_protocol'] = 'ospf';
            $payload['ospf_process_id'] = 1;
            $recommendations[] = 'Configure OSPF with area 0';
        }

        if ($device->status === 'offline') {
            $warnings[] = 'Device is currently offline';
        }

        return [
            'playbook_name' => $playbook,
            'goal' => $goal ?: 'custom',
            'preset_key' => '',
            'payload' => $payload,
            'recommendations' => $recommendations,
            'warnings' => $warnings,
            'summary' => "Processed intent: {$intentText}",
        ];
    }

    private function getDefaultInterfaceForDevice(Device $device): string
    {
        $platform = strtolower($device->platform ?? '');
        $connection = strtolower($device->connection ?? '');

        if (str_contains($platform, 'switch') || str_contains($platform, 'catalyst')) {
            return 'FastEthernet0/1';
        }
        if (str_contains($platform, 'router') || str_contains($platform, 'isr')) {
            return 'GigabitEthernet0/0';
        }
        if (str_contains($platform, 'firewall') || str_contains($platform, 'asa')) {
            return 'GigabitEthernet0/0';
        }
        if (str_contains($platform, 'wireless')) {
            return 'DotRadio0';
        }

        return 'GigabitEthernet0/0';
    }

    private function buildDefaultPlan(array $input): array
    {
        $goal = $input['goal'] ?? '';
        $playbookName = $this->selectPlaybookFromGoal($goal);

        if (! empty($input['playbook_name'])) {
            $playbookName = $input['playbook_name'];
        }

        return [
            'playbook_name' => $playbookName,
            'goal' => $goal ?: 'custom',
            'preset_key' => '',
            'payload' => $input['payload'] ?? [],
            'recommendations' => [],
            'warnings' => [],
            'summary' => 'Default deployment plan created',
        ];
    }

    private function selectPlaybookFromGoal(string $goal): ?string
    {
        $goalLower = strtolower($goal);

        if (str_contains($goalLower, 'vlan') || str_contains($goalLower, 'layer2')) {
            return 'vlan_setup.yml';
        }
        if (str_contains($goalLower, 'interface') || str_contains($goalLower, 'port') || str_contains($goalLower, 'access')) {
            return 'interface_config.yml';
        }
        if (str_contains($goalLower, 'routing') || str_contains($goalLower, 'ospf') || str_contains($goalLower, 'bgp')) {
            return 'routing_config.yml';
        }
        if (str_contains($goalLower, 'snmp') || str_contains($goalLower, 'monitoring')) {
            return 'snmp_config.yml';
        }
        if (str_contains($goalLower, 'rollback') || str_contains($goalLower, 'restore')) {
            return 'rollback.yml';
        }
        if (str_contains($goalLower, 'full') || str_contains($goalLower, 'complete') || str_contains($goalLower, 'deploy')) {
            return 'deployment.yml';
        }

        return null;
    }

    private function extractVlanId(string $text): int
    {
        if (preg_match('/vlan\s*(\d+)/i', $text, $matches)) {
            return (int) $matches[1];
        }

        return 10;
    }

    private function extractVlanName(?string $text): ?string
    {
        if (! $text) {
            return null;
        }

        if (preg_match('/named?\s+(\w+)/i', $text, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractInterface(string $text): string
    {
        if (preg_match('/(gigabitethernet|fastethernet|ethernet)\s*(\d+\/\d+)/i', $text, $matches)) {
            return strtolower($matches[1]) . $matches[2];
        }

        return 'GigabitEthernet0/1';
    }

    public function parseNaturalLanguage(string $text): array
    {
        $textLower = strtolower($text);
        $parsed = [
            'action' => 'configure',
            'targets' => [],
            'parameters' => [],
            'constraints' => [],
        ];

        if (str_contains($textLower, 'create') || str_contains($textLower, 'add')) {
            $parsed['action'] = 'create';
        } elseif (str_contains($textLower, 'delete') || str_contains($textLower, 'remove')) {
            $parsed['action'] = 'delete';
        } elseif (str_contains($textLower, 'update') || str_contains($textLower, 'modify')) {
            $parsed['action'] = 'update';
        }

        if (str_contains($textLower, 'vlan')) {
            $parsed['targets'][] = 'vlan';
            $parsed['parameters']['vlan_id'] = $this->extractVlanId($text);
        }

        if (str_contains($textLower, 'dhcp')) {
            $parsed['targets'][] = 'dhcp';
        }

        if (str_contains($textLower, 'routing') || str_contains($textLower, 'ospf')) {
            $parsed['targets'][] = 'routing';
            $parsed['parameters']['routing_protocol'] = 'ospf';
        }

        return $parsed;
    }

    public function presets(): array
    {
        return [
            'small_office' => [
                'key' => 'small_office',
                'label' => 'Small Office',
                'name' => 'Small Office',
                'description' => 'Basic office network with single VLAN and DHCP',
                'playbook' => 'vlan_setup.yml',
                'goal' => 'access_segmentation',
                'icon' => 'bi-building',
            ],
            'enterprise' => [
                'key' => 'enterprise',
                'label' => 'Enterprise',
                'name' => 'Enterprise',
                'description' => 'Full enterprise network with OSPF routing',
                'playbook' => 'deployment.yml',
                'goal' => 'multi_site_routing',
                'icon' => 'bi-building-up',
            ],
            'lab' => [
                'key' => 'lab',
                'label' => 'Lab',
                'name' => 'Lab',
                'description' => 'Network lab with multiple VLANs',
                'playbook' => 'vlan_setup.yml',
                'goal' => 'access_segmentation',
                'icon' => 'bi-mortarboard',
            ],
            'datacenter' => [
                'key' => 'datacenter',
                'label' => 'Datacenter',
                'name' => 'Datacenter',
                'description' => 'Datacenter with BGP routing',
                'playbook' => 'deployment.yml',
                'goal' => 'multi_site_routing',
                'icon' => 'bi-hdd',
            ],
        ];
    }
}