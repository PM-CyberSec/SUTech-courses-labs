<?php

namespace App\Services\Topology;

class TopologyLayoutService
{
    public function layout(array $devices): array
    {
        $rows = [
            'router' => 120,
            'firewall' => 120,
            'cloud' => 120,
            'switch' => 280,
            'server' => 440,
            'pc' => 440,
        ];

        $columns = [
            'router' => 220,
            'firewall' => 420,
            'cloud' => 620,
            'switch' => 200,
            'server' => 320,
            'pc' => 520,
        ];

        $counters = [];

        foreach ($devices as &$device) {
            $type = strtolower((string) ($device['type'] ?? 'pc'));
            $slot = $counters[$type] ?? 0;
            $counters[$type] = $slot + 1;

            $device['x_position'] = $columns[$type] ?? 520;
            $device['y_position'] = ($rows[$type] ?? 360) + ($slot * 120);
        }

        return $devices;
    }
}