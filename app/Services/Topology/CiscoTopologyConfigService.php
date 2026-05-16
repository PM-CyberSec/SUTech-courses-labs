<?php

namespace App\Services\Topology;

class CiscoTopologyConfigService
{
    public function generate(array $topology, array $vlans, array $ipPlan, array $routingPlan): array
    {
        $configs = [];

        foreach ($topology['devices'] as $device) {
            $type = strtolower((string) $device['type']);
            $configs[$device['name']] = match ($type) {
                'switch' => $this->buildSwitchConfig($device, $vlans, $ipPlan),
                'pc', 'server', 'firewall', 'cloud' => $this->buildEndpointConfig($device, $ipPlan, $topology),
                default => $this->buildRouterConfig($device, $vlans, $ipPlan, $routingPlan, $topology),
            };
        }

        return $configs;
    }

    private function buildSwitchConfig(array $device, array $vlans, array $ipPlan): string
    {
        $lines = [
            'hostname '.$device['name'],
            'no ip domain-lookup',
            'service password-encryption',
        ];

        foreach ($vlans as $vlan) {
            $lines[] = 'vlan '.$vlan['id'];
            $lines[] = ' name '.$vlan['name'];
        }

        foreach ($device['interfaces'] as $interface) {
            $lines[] = 'interface '.$interface['name'];
            if (($interface['mode'] ?? 'access') === 'trunk') {
                $allowed = $interface['allowed_vlans'] ?? implode(',', array_map(fn ($vlan) => (string) $vlan['id'], $vlans));
                $lines[] = ' switchport mode trunk';
                $lines[] = ' switchport trunk allowed vlan '.$allowed;
            } else {
                $lines[] = ' switchport mode access';
                if (! empty($interface['vlan_id'])) {
                    $lines[] = ' switchport access vlan '.$interface['vlan_id'];
                }
            }
            $lines[] = ' no shutdown';
            $lines[] = ' exit';
        }

        $managementVlan = $ipPlan['management_vlan'] ?? 99;
        $managementSubnet = $ipPlan['management_subnet'] ?? '192.168.99.0/24';
        [$managementNetwork] = explode('/', $managementSubnet);
        $lines[] = 'interface vlan '.$managementVlan;
        $lines[] = ' ip address '.preg_replace('/\.0$/', '.2', $managementNetwork).' 255.255.255.0';
        $lines[] = ' no shutdown';
        $lines[] = 'exit';

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function buildRouterConfig(array $device, array $vlans, array $ipPlan, array $routingPlan, array $topology): string
    {
        $lines = [
            'hostname '.$device['name'],
            'no ip domain-lookup',
            'service password-encryption',
            'enable secret class',
        ];

        foreach ($device['interfaces'] as $interface) {
            $lines[] = 'interface '.$interface['name'];
            if (($interface['mode'] ?? 'routed') === 'trunk') {
                $lines[] = ' no shutdown';
            } elseif (! empty($interface['subinterfaces'])) {
                $lines[] = ' no shutdown';
                foreach ($interface['subinterfaces'] as $subinterface) {
                    $lines[] = 'interface '.$subinterface['name'];
                    $lines[] = ' encapsulation dot1Q '.$subinterface['vlan_id'];
                    $lines[] = ' ip address '.$subinterface['ip_address'].' '.$subinterface['subnet_mask'];
                    if (! empty($subinterface['ospf_md5'])) {
                        $lines[] = ' ip ospf message-digest-key 1 md5 MD5pa55';
                    }
                    $lines[] = ' exit';
                }
            } else {
                if (! empty($interface['ip_address'])) {
                    $lines[] = ' ip address '.$interface['ip_address'].' '.$interface['subnet_mask'];
                }
                $lines[] = ' no shutdown';
            }
            $lines[] = ' exit';
        }

        if (($routingPlan['protocol'] ?? 'static') === 'ospf') {
            $lines[] = 'router ospf '.($routingPlan['process_id'] ?? 1);
            foreach ($routingPlan['ospf_networks'] as $network) {
                $lines[] = ' network '.$network['network'].' '.$network['wildcard'].' area '.($network['area'] ?? 0);
            }
            if (! empty($routingPlan['ospf_md5'])) {
                $lines[] = ' area 0 authentication message-digest';
            }
            $lines[] = 'exit';
        } elseif (($routingPlan['protocol'] ?? 'static') === 'static') {
            foreach ($routingPlan['static_routes'] as $route) {
                $lines[] = 'ip route '.$route['destination'].' '.$route['mask'].' '.$route['next_hop'];
            }
        }

        if (($topology['services']['dhcp'] ?? false) === true) {
            foreach ($vlans as $vlan) {
                $lines[] = 'ip dhcp pool VLAN'.$vlan['id'];
                $lines[] = ' network '.explode('/', $vlan['subnet'])[0].' 255.255.255.0';
                $lines[] = ' default-router '.$vlan['gateway'];
                if (! empty($topology['services']['dns'])) {
                    $lines[] = ' dns-server 192.168.10.10';
                }
                $lines[] = 'exit';
            }
        }

        if (($topology['services']['ssh'] ?? false) === true) {
            $lines[] = 'ip domain-name autoconfiglab.local';
            $lines[] = 'username admin secret Admin123!';
            $lines[] = 'crypto key generate rsa';
            $lines[] = 'ip ssh version 2';
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function buildEndpointConfig(array $device, array $ipPlan, array $topology): string
    {
        $lines = [
            'device '.$device['name'],
            'type '.$device['type'],
        ];

        if (! empty($device['interfaces'][0]['ip_address'])) {
            $lines[] = 'ip address '.$device['interfaces'][0]['ip_address'].' '.$device['interfaces'][0]['subnet_mask'];
        }

        if (! empty($device['gateway'])) {
            $lines[] = 'default gateway '.$device['gateway'];
        }

        if (! empty($topology['services']['dns'])) {
            $lines[] = 'dns server 192.168.10.10';
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }
}