<?php

namespace App\Services\Cisco;

use App\Models\Device;
use App\Models\Inventory;
use App\Services\Smart\CiscoTemplateEngine;
use App\Services\Smart\ValidationService;

class CiscoConfigGenerationService
{
    public function __construct(
        private readonly VlanConfigService $vlanConfigService,
        private readonly SwitchConfigService $switchConfigService,
        private readonly RouterConfigService $routerConfigService,
        private readonly RoutingConfigService $routingConfigService,
        private readonly AclConfigService $aclConfigService,
        private readonly DhcpDnsHttpScenarioService $scenarioService,
        private readonly SecurityConfigService $securityConfigService,
        private readonly SimulationGuideService $simulationGuideService,
        private readonly CiscoTemplateEngine $templateEngine,
        private readonly ValidationService $validationService
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{config:string, validation:array{errors:array<int,string>,warnings:array<int,string>}, simulation_steps:array<int,string>}
     */
    public function generate(Device $device, ?Inventory $inventory, array $payload = []): array
    {
        $scenarioKey = (string) ($payload['scenario_key'] ?? '');
        $scenario = array_replace_recursive($this->defaultScenario($device, $inventory, $scenarioKey), $payload);
        $validation = $this->validationService->validateForDeployment($device, $scenario);

        $lines = [];
        $lines[] = 'enable';
        $lines[] = 'configure terminal';

        $lines = array_merge($lines, $this->vlanConfigService->build($scenario['vlans'] ?? []));
        $lines = array_merge($lines, $this->switchConfigService->build(
            $scenario['switch_interfaces'] ?? [],
            $scenario['vlans'] ?? [],
            $scenario['management_vlan'] ?? null,
            $scenario['management_ip'] ?? null,
            $scenario['management_mask'] ?? null
        ));
        $lines = array_merge($lines, $this->routerConfigService->build($scenario['router_interfaces'] ?? []));
        $lines = array_merge($lines, $this->routingConfigService->build($scenario['routing'] ?? []));
        $lines = array_merge($lines, $this->scenarioService->build($scenario['services'] ?? []));
        $lines = array_merge($lines, $this->securityConfigService->build($scenario['security'] ?? []));
        $lines = array_merge($lines, $this->aclConfigService->build($scenario['acls'] ?? []));

        if (! empty($scenario['custom_template'])) {
            $lines[] = $this->templateEngine->render((string) $scenario['custom_template'], $scenario);
        }

        $lines[] = 'end';
        $lines[] = 'write memory';

        return [
            'config' => implode(PHP_EOL, array_values(array_filter($lines, fn ($line) => $line !== ''))),
            'validation' => $validation,
            'simulation_steps' => $this->simulationGuideService->steps($scenario),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presetScenarios(): array
    {
        return [
            'vlan_lab' => [
                'label' => 'VLAN Lab',
                'description' => 'Create VLANs, access ports, trunk ports, and a management SVI.',
                'scenario_name' => 'VLAN Lab',
            ],
            'router_static_lab' => [
                'label' => 'Router Static Routing Lab',
                'description' => 'Configure router interfaces and static routes.',
                'scenario_name' => 'Router Static Routing Lab',
            ],
            'dhcp_dns_http_lab' => [
                'label' => 'DHCP/DNS/HTTP Lab',
                'description' => 'Build a services scenario for Packet Tracer labs.',
                'scenario_name' => 'DHCP/DNS/HTTP Lab',
            ],
            'ospf_md5_lab' => [
                'label' => 'OSPF MD5 Lab',
                'description' => 'Secure OSPF with MD5 authentication.',
                'scenario_name' => 'OSPF MD5 Lab',
            ],
            'ssh_ntp_syslog_lab' => [
                'label' => 'SSH/NTP/Syslog Lab',
                'description' => 'Remote access and operations hardening.',
                'scenario_name' => 'SSH/NTP/Syslog Lab',
            ],
            'aaa_lab' => [
                'label' => 'AAA Lab',
                'description' => 'Local AAA, TACACS+, and RADIUS examples.',
                'scenario_name' => 'AAA Lab',
            ],
            'acl_firewall_lab' => [
                'label' => 'ACL Firewall Lab',
                'description' => 'Access lists and interface direction controls.',
                'scenario_name' => 'ACL Firewall Lab',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultScenario(Device $device, ?Inventory $inventory, string $scenarioKey): array
    {
        return $this->scenarioDefaults($device, $inventory, $scenarioKey);
    }

    /**
     * @return array<string, mixed>
     */
    private function scenarioDefaults(Device $device, ?Inventory $inventory, string $scenarioKey): array
    {
        $base = [
            'hostname' => $device->hostname,
            'vlans' => [['id' => 10, 'name' => 'VLAN10']],
            'switch_interfaces' => [
                ['name' => 'fa0/2', 'mode' => 'access', 'access_vlan' => 10, 'shutdown' => false],
                ['name' => 'fa0/1', 'mode' => 'trunk', 'allowed_vlans' => 'all', 'shutdown' => false],
            ],
            'router_interfaces' => [
                ['name' => 'fa0/0', 'ip' => '192.168.1.1', 'mask' => '255.255.255.0', 'shutdown' => false],
            ],
            'routing' => [
                'protocol' => 'static',
                'static_routes' => [
                    ['destination' => '10.10.10.0', 'mask' => '255.255.255.0', 'next_hop' => '192.168.0.1'],
                ],
            ],
            'services' => [
                'dhcp' => [
                    'pool_name' => 'LAN_POOL',
                    'network' => '172.16.0.0',
                    'mask' => '255.255.255.0',
                    'gateway' => '172.16.0.1',
                    'dns' => '172.16.0.11',
                    'start_ip' => '172.16.0.100',
                ],
                'dns_records' => [],
                'web_servers' => [],
            ],
            'security' => [
                'domain_name' => 'ccnasecurity.com',
                'username' => 'SSHadmin',
                'password' => 'ciscosshpa55',
                'privilege' => 15,
                'ssh' => true,
                'rsa_bits' => 1024,
                'ssh_version' => 2,
                'timeout' => 90,
                'retries' => 2,
                'aaa_mode' => 'local',
                'ntp_server' => '192.168.1.5',
                'ntp_key' => 'NTPpa55',
                'syslog_server' => '192.168.1.6',
            ],
            'acls' => [
                [
                    'number' => 100,
                    'action' => 'permit',
                    'protocol' => 'tcp',
                    'source' => '172.22.34.64 0.0.0.31',
                    'destination' => 'host 172.22.34.62',
                    'port' => 'ftp',
                ],
            ],
            'management_vlan' => 99,
            'management_ip' => '10.10.10.99',
            'management_mask' => '255.255.255.0',
        ];

        return match ($scenarioKey) {
            'router_static_lab' => [
                'hostname' => $device->hostname,
                'router_interfaces' => [
                    ['name' => 'fa0/0', 'ip' => '192.168.1.1', 'mask' => '255.255.255.0', 'shutdown' => false],
                    ['name' => 's2/0', 'ip' => '192.168.2.1', 'mask' => '255.255.255.0', 'shutdown' => false],
                ],
                'routing' => [
                    'protocol' => 'static',
                    'default_route' => true,
                    'default_next_hop' => '192.168.0.1',
                    'static_routes' => [
                        ['destination' => '10.10.10.0', 'mask' => '255.255.255.0', 'next_hop' => '192.168.0.1'],
                    ],
                ],
            ],
            'dhcp_dns_http_lab' => [
                'hostname' => $device->hostname,
                'services' => [
                    'dhcp' => [
                        'pool_name' => 'TSRB_POOL',
                        'network' => '172.16.0.0',
                        'mask' => '255.255.255.0',
                        'gateway' => '172.16.0.1',
                        'dns' => '172.16.0.11',
                        'start_ip' => '172.16.0.100',
                    ],
                    'dns_records' => [
                        ['host' => 'www.tsrb.edu', 'ip' => '172.16.0.20'],
                        ['host' => 'www.internal.com', 'ip' => '172.16.0.30'],
                    ],
                    'web_servers' => [
                        ['host' => 'www.tsrb.edu', 'ip' => '172.16.0.20'],
                        ['host' => 'www.internal.com', 'ip' => '172.16.0.30'],
                    ],
                ],
            ],
            'ospf_md5_lab' => [
                'hostname' => $device->hostname,
                'routing' => [
                    'protocol' => 'ospf',
                    'ospf' => [
                        'process_id' => 1,
                        'networks' => [['network' => '10.0.0.0', 'wildcard' => '0.0.0.255', 'area' => 0]],
                        'md5_enabled' => true,
                        'interfaces' => [
                            ['name' => 's0/0/0', 'key_id' => 1, 'md5_password' => 'MD5pa55'],
                        ],
                    ],
                ],
            ],
            'ssh_ntp_syslog_lab' => [
                'hostname' => $device->hostname,
                'security' => [
                    'domain_name' => 'ccnasecurity.com',
                    'username' => 'SSHadmin',
                    'password' => 'ciscosshpa55',
                    'privilege' => 15,
                    'ssh' => true,
                    'rsa_bits' => 1024,
                    'ssh_version' => 2,
                    'timeout' => 90,
                    'retries' => 2,
                    'ntp_server' => '192.168.1.5',
                    'ntp_key' => 'NTPpa55',
                    'syslog_server' => '192.168.1.6',
                ],
            ],
            'aaa_lab' => [
                'hostname' => $device->hostname,
                'security' => [
                    'domain_name' => 'ccnasecurity.com',
                    'username' => 'Admin1',
                    'password' => 'admin1pa55',
                    'privilege' => 15,
                    'ssh' => true,
                    'rsa_bits' => 1024,
                    'aaa_mode' => 'local',
                ],
            ],
            'acl_firewall_lab' => [
                'hostname' => $device->hostname,
                'acls' => [
                    [
                        'number' => 100,
                        'action' => 'permit',
                        'protocol' => 'tcp',
                        'source' => '172.22.34.64 0.0.0.31',
                        'destination' => 'host 172.22.34.62',
                        'port' => 'ftp',
                    ],
                    [
                        'number' => 100,
                        'action' => 'permit',
                        'protocol' => 'icmp',
                        'source' => 'any',
                        'destination' => 'any',
                    ],
                ],
            ],
            default => array_replace_recursive($base, [
                'default_gateway' => $inventory?->variables['default_gateway'] ?? null,
            ]),
        };
    }
}