<?php

namespace App\Services\Topology;

class VlanPlanGenerationService
{
    public function generate(array $blueprint): array
    {
        $vlans = $blueprint['vlans'] ?? [];

        if ($vlans === []) {
            $vlans = [['id' => 10, 'name' => 'USERS']];
        }

        if (($blueprint['counts']['switches'] ?? 0) > 0 && ! collect($vlans)->contains(fn ($vlan) => (int) ($vlan['id'] ?? 0) === 99)) {
            $vlans[] = ['id' => 99, 'name' => 'MGMT'];
        }

        return array_values(array_map(function (array $vlan): array {
            $vlanId = (int) $vlan['id'];

            return [
                'id' => $vlanId,
                'name' => $vlan['name'] ?? 'VLAN'.$vlanId,
                'subnet' => sprintf('192.168.%d.0/24', $vlanId),
                'gateway' => sprintf('192.168.%d.1', $vlanId),
                'dhcp_start' => sprintf('192.168.%d.100', $vlanId),
            ];
        }, $vlans));
    }
}