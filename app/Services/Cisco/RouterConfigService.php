<?php

namespace App\Services\Cisco;

class RouterConfigService
{
    /**
     * @param  array<int, array{name:string,ip?:string,mask?:string,shutdown?:bool}>  $interfaces
     * @return array<int, string>
     */
    public function build(array $interfaces): array
    {
        $lines = [];

        foreach ($interfaces as $interface) {
            if (empty($interface['name'])) {
                continue;
            }

            $lines[] = "interface {$interface['name']}";
            if (! empty($interface['ip']) && ! empty($interface['mask'])) {
                $lines[] = " ip address {$interface['ip']} {$interface['mask']}";
            }
            $lines[] = ! empty($interface['shutdown']) ? ' shutdown' : ' no shutdown';
            $lines[] = ' exit';
        }

        return $lines;
    }
}