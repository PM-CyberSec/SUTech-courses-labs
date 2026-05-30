<?php

namespace App\Services\Topology;

class IpPlanGenerationService
{
    public function generate(array $blueprint, array $vlans): array
    {
        $plan = [
            'lan_subnet' => $blueprint['ip_plan']['lan_subnet'] ?? '192.168.1.0/24',
            'management_vlan' => $blueprint['ip_plan']['management_vlan'] ?? 99,
            'management_subnet' => $blueprint['ip_plan']['management_subnet'] ?? '192.168.99.0/24',
            'links' => [],
            'end_devices' => [],
        ];

        foreach ($vlans as $vlan) {
            $plan['end_devices'][$vlan['id']] = [
                'subnet' => $vlan['subnet'],
                'gateway' => $vlan['gateway'],
                'dhcp_start' => $vlan['dhcp_start'],
            ];
        }

        $linkIndex = 1;
        for ($i = 1; $i <= max(1, (int) ($blueprint['counts']['routers'] ?? 1) - 1); $i++) {
            $plan['links'][] = [
                'network' => sprintf('10.0.%d.0/30', $linkIndex),
                'a_ip' => sprintf('10.0.%d.1', $linkIndex),
                'b_ip' => sprintf('10.0.%d.2', $linkIndex),
                'mask' => '255.255.255.252',
            ];
            $linkIndex++;
        }

        return $plan;
    }
}