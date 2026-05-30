<?php

namespace App\Services\Cisco;

class SwitchConfigService
{
    /**
     * @param  array<int, array<string, mixed>>  $interfaces
     * @param  array<int, array{id:int,name?:string}>  $vlans
     * @return array<int, string>
     */
    public function build(array $interfaces, array $vlans, ?int $managementVlan = null, ?string $managementIp = null, ?string $managementMask = null): array
    {
        $lines = [];

        foreach ($interfaces as $interface) {
            if (empty($interface['name'])) {
                continue;
            }

            $lines[] = "interface {$interface['name']}";
            $mode = strtolower((string) ($interface['mode'] ?? 'access'));

            if ($mode === 'trunk') {
                $lines[] = ' switchport mode trunk';
                $allowedVlans = $interface['allowed_vlans'] ?? 'all';
                $lines[] = " switchport trunk allowed vlan {$allowedVlans}";
            } else {
                $lines[] = ' switchport mode access';
                if (! empty($interface['access_vlan'])) {
                    $lines[] = " switchport access vlan {$interface['access_vlan']}";
                }
            }

            $lines[] = ! empty($interface['shutdown']) ? ' shutdown' : ' no shutdown';
            $lines[] = ' exit';
        }

        if ($managementVlan !== null) {
            $lines[] = "interface vlan {$managementVlan}";
            if ($managementIp && $managementMask) {
                $lines[] = " ip address {$managementIp} {$managementMask}";
            }
            $lines[] = ' no shutdown';
            $lines[] = ' exit';
        }

        return $lines;
    }
}