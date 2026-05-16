<?php

namespace App\Services\Cisco;

class VlanConfigService
{
    /**
     * @param  array<int, array{id:int,name?:string}>  $vlans
     * @return array<int, string>
     */
    public function build(array $vlans): array
    {
        $lines = [];

        foreach ($vlans as $vlan) {
            if (empty($vlan['id'])) {
                continue;
            }

            $lines[] = "vlan {$vlan['id']}";
            if (! empty($vlan['name'])) {
                $lines[] = " name {$vlan['name']}";
            }
            $lines[] = ' exit';
        }

        return $lines;
    }
}