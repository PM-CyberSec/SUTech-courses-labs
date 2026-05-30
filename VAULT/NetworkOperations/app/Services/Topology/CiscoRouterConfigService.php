<?php

namespace App\Services\Topology;

use App\Models\TopologyDevice;
use Illuminate\Support\Collection;

class CiscoRouterConfigService
{
    public function generateRouterConfig(TopologyDevice $device, Collection $interfaces): string
    {
        $lines = [];
        $lines[] = "hostname {$device->hostname}";
        $lines[] = 'enable secret '.($device->enable_secret ?: 'class');

        if ($device->service_password_encryption) {
            $lines[] = 'service password-encryption';
        }

        $consolePassword = $device->console_password ?: 'cisco';
        $vtyPassword = $device->vty_password ?: 'cisco';

        $lines[] = 'line console 0';
        $lines[] = " password {$consolePassword}";
        $lines[] = ' login';
        $lines[] = ' exit';

        $lines[] = 'line vty 0 4';
        $lines[] = " password {$vtyPassword}";
        $lines[] = ' login';
        $lines[] = ' exit';

        if ($device->device_type === 'multilayer_switch') {
            $lines[] = 'ip routing';
        }

        $lines = array_merge($lines, $this->buildInterfaceSection($device, $interfaces));
        $lines = array_merge($lines, $this->buildStaticRoutes($device));
        $lines = array_merge($lines, $this->buildDynamicRouting($device, $interfaces));
        $lines = array_merge($lines, $this->buildDhcpPools($device));
        $lines = array_merge($lines, $this->buildNat($device));
        $lines = array_merge($lines, $this->buildAcls($device));
        $lines = array_merge($lines, $this->buildSshSection($device));

        if ($device->default_gateway && $device->device_type === 'router') {
            $lines[] = "ip route 0.0.0.0 0.0.0.0 {$device->default_gateway}";
        }

        $lines[] = 'end';
        $lines[] = 'write memory';

        return implode(PHP_EOL, array_values(array_filter($lines, fn ($line) => $line !== '')));
    }

    /**
     * @return array<int, string>
     */
    private function buildInterfaceSection(TopologyDevice $device, Collection $interfaces): array
    {
        $lines = [];
        $nat = is_array($device->nat_rules) ? $device->nat_rules : [];
        $inside = collect($nat['inside_interfaces'] ?? [])->map(fn ($name) => strtolower((string) $name))->all();
        $outside = collect($nat['outside_interfaces'] ?? [])->map(fn ($name) => strtolower((string) $name))->all();

        foreach ($interfaces as $interface) {
            $name = trim((string) $interface->name);
            if ($name === '') {
                continue;
            }

            $mode = strtolower((string) ($interface->mode ?? 'routed'));
            $lines[] = "interface {$name}";

            if (! empty($interface->description)) {
                $lines[] = ' description '.$interface->description;
            }

            if ($device->device_type === 'multilayer_switch' && in_array($mode, ['access', 'trunk'], true)) {
                if ($mode === 'trunk') {
                    $lines[] = ' switchport mode trunk';
                    if (! empty($interface->allowed_vlans)) {
                        $lines[] = ' switchport trunk allowed vlan '.$interface->allowed_vlans;
                    }
                    if (! empty($interface->native_vlan)) {
                        $lines[] = ' switchport trunk native vlan '.$interface->native_vlan;
                    }
                } else {
                    $lines[] = ' switchport mode access';
                    if (! empty($interface->vlan_id)) {
                        $lines[] = " switchport access vlan {$interface->vlan_id}";
                    }
                }
            } else {
                if ($device->device_type === 'multilayer_switch') {
                    $lines[] = ' no switchport';
                }
                if (! empty($interface->ip_address) && ! empty($interface->subnet_mask)) {
                    $lines[] = " ip address {$interface->ip_address} {$interface->subnet_mask}";
                }
            }

            if (in_array(strtolower($name), $inside, true)) {
                $lines[] = ' ip nat inside';
            }
            if (in_array(strtolower($name), $outside, true)) {
                $lines[] = ' ip nat outside';
            }

            $lines[] = $interface->is_shutdown ? ' shutdown' : ' no shutdown';
            $lines[] = ' exit';
        }

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    private function buildStaticRoutes(TopologyDevice $device): array
    {
        $lines = [];
        $routes = is_array($device->static_routes) ? $device->static_routes : [];

        foreach ($routes as $route) {
            $destination = trim((string) ($route['destination'] ?? ''));
            $mask = trim((string) ($route['mask'] ?? ''));
            $nextHop = trim((string) ($route['next_hop'] ?? ''));
            if ($destination === '' || $mask === '' || $nextHop === '') {
                continue;
            }

            $lines[] = "ip route {$destination} {$mask} {$nextHop}";
        }

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    private function buildDynamicRouting(TopologyDevice $device, Collection $interfaces): array
    {
        $lines = [];
        $protocol = strtolower((string) ($device->routing_protocol ?? ''));
        $metadata = is_array($device->metadata) ? $device->metadata : [];

        if ($protocol === 'rip') {
            $networks = $metadata['rip_networks'] ?? $this->deriveNetworks($interfaces);
            $lines[] = 'router rip';
            $lines[] = ' version 2';
            foreach ($networks as $network) {
                $lines[] = " network {$network}";
            }
            $lines[] = ' exit';
        }

        if ($protocol === 'ospf') {
            $processId = (int) ($metadata['ospf']['process_id'] ?? 1);
            $networks = $metadata['ospf']['networks'] ?? $this->deriveWildcardNetworks($interfaces);
            $lines[] = "router ospf {$processId}";
            foreach ($networks as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $network = $entry['network'] ?? null;
                $wildcard = $entry['wildcard'] ?? null;
                $area = $entry['area'] ?? 0;
                if (! $network || ! $wildcard) {
                    continue;
                }
                $lines[] = " network {$network} {$wildcard} area {$area}";
            }
            $lines[] = ' exit';
        }

        if ($protocol === 'eigrp') {
            $asn = (int) ($metadata['eigrp']['asn'] ?? 100);
            $networks = $metadata['eigrp']['networks'] ?? $this->deriveWildcardNetworkStrings($interfaces);
            $lines[] = "router eigrp {$asn}";
            $lines[] = ' no auto-summary';
            foreach ($networks as $network) {
                $lines[] = " network {$network}";
            }
            $lines[] = ' exit';
        }

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    private function buildDhcpPools(TopologyDevice $device): array
    {
        $lines = [];
        $pools = is_array($device->dhcp_pools) ? $device->dhcp_pools : [];

        foreach ($pools as $pool) {
            $name = trim((string) ($pool['pool_name'] ?? ''));
            $network = trim((string) ($pool['network'] ?? ''));
            $mask = trim((string) ($pool['mask'] ?? ''));
            if ($name === '' || $network === '' || $mask === '') {
                continue;
            }

            $lines[] = "ip dhcp pool {$name}";
            $lines[] = " network {$network} {$mask}";
            if (! empty($pool['default_router'])) {
                $lines[] = ' default-router '.$pool['default_router'];
            }
            if (! empty($pool['dns_server'])) {
                $lines[] = ' dns-server '.$pool['dns_server'];
            }
            $lines[] = ' exit';
        }

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    private function buildNat(TopologyDevice $device): array
    {
        $lines = [];
        $nat = is_array($device->nat_rules) ? $device->nat_rules : [];

        if (! empty($nat['dynamic']) && is_array($nat['dynamic'])) {
            $dyn = $nat['dynamic'];
            $aclNo = (int) ($dyn['acl'] ?? 1);
            if (! empty($dyn['network']) && ! empty($dyn['wildcard'])) {
                $lines[] = "access-list {$aclNo} permit {$dyn['network']} {$dyn['wildcard']}";
            }
            if (! empty($dyn['overload_interface'])) {
                $lines[] = "ip nat inside source list {$aclNo} interface {$dyn['overload_interface']} overload";
            }
        }

        foreach (($nat['static'] ?? []) as $staticNat) {
            $insideLocal = trim((string) ($staticNat['inside_local'] ?? ''));
            $insideGlobal = trim((string) ($staticNat['inside_global'] ?? ''));
            if ($insideLocal === '' || $insideGlobal === '') {
                continue;
            }
            $lines[] = "ip nat inside source static {$insideLocal} {$insideGlobal}";
        }

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    private function buildAcls(TopologyDevice $device): array
    {
        $lines = [];
        $acls = is_array($device->acl_rules) ? $device->acl_rules : [];
        foreach ($acls as $acl) {
            $number = trim((string) ($acl['number'] ?? ''));
            $action = trim((string) ($acl['action'] ?? ''));
            $source = trim((string) ($acl['source'] ?? ''));
            if ($number === '' || $action === '' || $source === '') {
                continue;
            }
            $destination = trim((string) ($acl['destination'] ?? ''));
            $lines[] = trim("access-list {$number} {$action} {$source} {$destination}");
        }

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    private function buildSshSection(TopologyDevice $device): array
    {
        $lines = [];
        $ssh = is_array($device->ssh_settings) ? $device->ssh_settings : [];
        $enabled = (bool) ($ssh['enabled'] ?? true);

        if (! $enabled) {
            return $lines;
        }

        $username = (string) ($ssh['username'] ?? 'netops');
        $password = (string) ($ssh['password'] ?? 'cisco123');
        $domain = (string) ($ssh['domain'] ?? 'autoconfiglab.local');
        $rsaBits = (int) ($ssh['rsa_bits'] ?? 1024);

        $lines[] = "username {$username} privilege 15 secret {$password}";
        $lines[] = "ip domain-name {$domain}";
        $lines[] = "crypto key generate rsa modulus {$rsaBits}";
        $lines[] = 'ip ssh version 2';
        $lines[] = 'line vty 0 4';
        $lines[] = ' login local';
        $lines[] = ' transport input ssh';
        $lines[] = ' exit';

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    private function deriveNetworks(Collection $interfaces): array
    {
        $networks = [];
        foreach ($interfaces as $interface) {
            if (empty($interface->ip_address) || empty($interface->subnet_mask)) {
                continue;
            }
            $network = $this->networkAddress($interface->ip_address, $interface->subnet_mask);
            if ($network) {
                $networks[] = $network;
            }
        }

        return array_values(array_unique($networks));
    }

    /**
     * @return array<int, array{network:string, wildcard:string, area:int}>
     */
    private function deriveWildcardNetworks(Collection $interfaces): array
    {
        $networks = [];
        foreach ($interfaces as $interface) {
            if (empty($interface->ip_address) || empty($interface->subnet_mask)) {
                continue;
            }

            $network = $this->networkAddress($interface->ip_address, $interface->subnet_mask);
            $wildcard = $this->wildcardMask($interface->subnet_mask);
            if (! $network || ! $wildcard) {
                continue;
            }

            $networks[] = [
                'network' => $network,
                'wildcard' => $wildcard,
                'area' => 0,
            ];
        }

        return $networks;
    }

    /**
     * @return array<int, string>
     */
    private function deriveWildcardNetworkStrings(Collection $interfaces): array
    {
        $results = [];
        foreach ($this->deriveWildcardNetworks($interfaces) as $entry) {
            $results[] = $entry['network'].' '.$entry['wildcard'];
        }

        return array_values(array_unique($results));
    }

    private function networkAddress(string $ip, string $mask): ?string
    {
        $ipLong = ip2long($ip);
        $maskLong = ip2long($mask);
        if ($ipLong === false || $maskLong === false) {
            return null;
        }

        return long2ip($ipLong & $maskLong) ?: null;
    }

    private function wildcardMask(string $mask): ?string
    {
        $maskLong = ip2long($mask);
        if ($maskLong === false) {
            return null;
        }

        return long2ip(~$maskLong & 0xFFFFFFFF) ?: null;
    }
}
