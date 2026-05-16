<?php

namespace App\Services\Cisco;

class RoutingConfigService
{
    /**
     * @param  array<string, mixed>  $routing
     * @return array<int, string>
     */
    public function build(array $routing): array
    {
        $lines = [];
        $protocol = strtolower((string) ($routing['protocol'] ?? ''));

        if (($routing['default_route'] ?? false) === true) {
            $lines[] = 'ip route 0.0.0.0 0.0.0.0 '.($routing['default_next_hop'] ?? '0.0.0.0');
        }

        if ($protocol === 'static') {
            foreach (($routing['static_routes'] ?? []) as $route) {
                if (! empty($route['destination']) && ! empty($route['mask']) && ! empty($route['next_hop'])) {
                    $lines[] = "ip route {$route['destination']} {$route['mask']} {$route['next_hop']}";
                }
            }
        }

        if ($protocol === 'ospf') {
            $processId = $routing['ospf']['process_id'] ?? 1;
            $lines[] = "router ospf {$processId}";
            foreach (($routing['ospf']['networks'] ?? []) as $network) {
                $lines[] = " network {$network['network']} {$network['wildcard']} area {$network['area']}";
            }
            if (($routing['ospf']['md5_enabled'] ?? false) === true) {
                $lines[] = ' area 0 authentication message-digest';
            }
            foreach (($routing['ospf']['interfaces'] ?? []) as $interface) {
                if (! empty($interface['name']) && ! empty($interface['key_id']) && ! empty($interface['md5_password'])) {
                    $lines[] = "interface {$interface['name']}";
                    $lines[] = " ip ospf message-digest-key {$interface['key_id']} md5 {$interface['md5_password']}";
                    $lines[] = ' exit';
                }
            }
            $lines[] = ' exit';
        }

        if ($protocol === 'rip') {
            $lines[] = 'router rip';
            $lines[] = ' version '.($routing['rip']['version'] ?? 2);
            foreach (($routing['rip']['networks'] ?? []) as $network) {
                $lines[] = " network {$network}";
            }
            $lines[] = ' exit';
        }

        if ($protocol === 'eigrp') {
            $asn = $routing['eigrp']['asn'] ?? 100;
            $lines[] = "router eigrp {$asn}";
            $lines[] = ' no auto-summary';
            foreach (($routing['eigrp']['networks'] ?? []) as $network) {
                $lines[] = " network {$network}";
            }
            $lines[] = ' exit';
        }

        return $lines;
    }
}