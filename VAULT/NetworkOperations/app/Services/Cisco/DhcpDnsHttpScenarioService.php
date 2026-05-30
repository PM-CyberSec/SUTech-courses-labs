<?php

namespace App\Services\Cisco;

class DhcpDnsHttpScenarioService
{
    /**
     * @param  array<string, mixed>  $scenario
     * @return array<int, string>
     */
    public function build(array $scenario): array
    {
        $lines = [];

        if (! empty($scenario['dhcp']['pool_name']) && ! empty($scenario['dhcp']['network']) && ! empty($scenario['dhcp']['mask'])) {
            $dhcp = $scenario['dhcp'];
            $lines[] = "ip dhcp pool {$dhcp['pool_name']}";
            $lines[] = " network {$dhcp['network']} {$dhcp['mask']}";
            if (! empty($dhcp['gateway'])) {
                $lines[] = " default-router {$dhcp['gateway']}";
            }
            if (! empty($dhcp['dns'])) {
                $lines[] = " dns-server {$dhcp['dns']}";
            }
            if (! empty($dhcp['start_ip'])) {
                $lines[] = '! DHCP start IP plan: '.$dhcp['start_ip'];
            }
            $lines[] = ' exit';
        }

        foreach (($scenario['dns_records'] ?? []) as $record) {
            if (! empty($record['host']) && ! empty($record['ip'])) {
                $lines[] = '! DNS '.$record['host'].' -> '.$record['ip'];
            }
        }

        foreach (($scenario['web_servers'] ?? []) as $server) {
            if (! empty($server['host']) && ! empty($server['ip'])) {
                $lines[] = '! Web server '.$server['host'].' at '.$server['ip'];
            }
        }

        return $lines;
    }
}