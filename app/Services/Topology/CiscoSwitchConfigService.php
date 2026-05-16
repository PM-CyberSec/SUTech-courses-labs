<?php

namespace App\Services\Topology;

use App\Models\TopologyDevice;
use Illuminate\Support\Collection;

class CiscoSwitchConfigService
{
    public function generateSwitchConfig(TopologyDevice $device, Collection $interfaces, bool $appendFooter = true): string
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

        $lines = array_merge($lines, $this->buildVlanSection($device));
        $lines = array_merge($lines, $this->buildInterfaceSection($interfaces));

        if ($device->default_gateway) {
            $lines[] = "ip default-gateway {$device->default_gateway}";
        }

        $lines = array_merge($lines, $this->buildSshSection($device));

        if ($appendFooter) {
            $lines[] = 'end';
            $lines[] = 'write memory';
        }

        return implode(PHP_EOL, array_values(array_filter($lines, fn ($line) => $line !== '')));
    }

    /**
     * @return array<int, string>
     */
    private function buildVlanSection(TopologyDevice $device): array
    {
        $lines = [];
        $vlans = is_array($device->vlans) ? $device->vlans : [];

        foreach ($vlans as $vlan) {
            $vlanId = (int) ($vlan['id'] ?? 0);
            if ($vlanId < 1) {
                continue;
            }

            $lines[] = "vlan {$vlanId}";
            if (! empty($vlan['name'])) {
                $lines[] = ' name '.$vlan['name'];
            }
            $lines[] = ' exit';
        }

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    private function buildInterfaceSection(Collection $interfaces): array
    {
        $lines = [];

        foreach ($interfaces as $interface) {
            $name = trim((string) $interface->name);
            if ($name === '') {
                continue;
            }

            $mode = strtolower((string) ($interface->mode ?? 'access'));
            $lines[] = "interface {$name}";

            if (! empty($interface->description)) {
                $lines[] = ' description '.$interface->description;
            }

            if ($mode === 'trunk') {
                $lines[] = ' switchport mode trunk';
                if (! empty($interface->allowed_vlans)) {
                    $lines[] = ' switchport trunk allowed vlan '.$interface->allowed_vlans;
                }
                if (! empty($interface->native_vlan)) {
                    $lines[] = ' switchport trunk native vlan '.$interface->native_vlan;
                }
            } elseif (in_array($mode, ['routed', 'layer3'], true)) {
                $lines[] = ' no switchport';
                if (! empty($interface->ip_address) && ! empty($interface->subnet_mask)) {
                    $lines[] = " ip address {$interface->ip_address} {$interface->subnet_mask}";
                }
            } else {
                $lines[] = ' switchport mode access';
                if (! empty($interface->vlan_id)) {
                    $lines[] = " switchport access vlan {$interface->vlan_id}";
                }
            }

            $lines[] = $interface->is_shutdown ? ' shutdown' : ' no shutdown';
            $lines[] = ' exit';
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
}
