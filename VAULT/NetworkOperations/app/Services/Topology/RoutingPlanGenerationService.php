<?php

namespace App\Services\Topology;

class RoutingPlanGenerationService
{
    public function generate(array $blueprint, array $vlans, array $ipPlan): array
    {
        $routingType = $blueprint['routing']['protocol'] ?? 'static';
        $plan = [
            'protocol' => $routingType,
            'process_id' => $blueprint['routing']['process_id'] ?? 1,
            'area' => $blueprint['routing']['area'] ?? 0,
            'ospf_md5' => (bool) ($blueprint['services']['ospf_md5'] ?? false),
            'static_routes' => [],
            'ospf_networks' => [],
        ];

        foreach ($vlans as $vlan) {
            $network = explode('/', $vlan['subnet'])[0];
            $plan['ospf_networks'][] = [
                'network' => $network,
                'wildcard' => '0.0.0.255',
                'area' => $plan['area'],
            ];
        }

        foreach ($ipPlan['links'] as $link) {
            $network = explode('/', $link['network'])[0];
            $plan['ospf_networks'][] = [
                'network' => $network,
                'wildcard' => '0.0.0.3',
                'area' => $plan['area'],
            ];
        }

        if ($routingType === 'static') {
            foreach ($vlans as $vlan) {
                $plan['static_routes'][] = [
                    'destination' => explode('/', $vlan['subnet'])[0],
                    'mask' => '255.255.255.0',
                    'next_hop' => $vlan['gateway'],
                ];
            }
        }

        return $plan;
    }
}