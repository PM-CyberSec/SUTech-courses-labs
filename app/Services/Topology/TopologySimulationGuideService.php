<?php

namespace App\Services\Topology;

class TopologySimulationGuideService
{
    public function generate(array $topology, array $vlans, array $routingPlan): array
    {
        $steps = [
            'Check device power and cable connections.',
            'Verify interface status with show ip interface brief.',
            'Confirm VLAN membership and trunking.',
            'Test end-device IP addressing and default gateways.',
        ];

        if (($routingPlan['protocol'] ?? 'static') === 'ospf') {
            $steps[] = 'Check OSPF neighbor relationships and route tables.';
        }

        if (($topology['services']['dhcp'] ?? false) === true) {
            $steps[] = 'Renew client leases and confirm DHCP scope assignment.';
        }

        if (($topology['services']['dns'] ?? false) === true) {
            $steps[] = 'Resolve hostnames from client PCs to verify DNS.';
        }

        if (($topology['services']['nat'] ?? false) === true) {
            $steps[] = 'Ping the internet cloud and confirm NAT translations.';
        }

        return $steps;
    }
}