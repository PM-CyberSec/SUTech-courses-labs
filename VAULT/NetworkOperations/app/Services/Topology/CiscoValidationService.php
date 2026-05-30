<?php

namespace App\Services\Topology;

use App\Models\Topology;
use App\Models\TopologyDevice;
use Illuminate\Support\Collection;

class CiscoValidationService
{
    /**
     * @return array{errors:array<int, string>, warnings:array<int, string>}
     */
    public function validateTopology(Topology $topology): array
    {
        $topology->loadMissing([
            'topologyDevices.interfaces',
            'topologyLinks.fromDevice.interfaces',
            'topologyLinks.toDevice.interfaces',
        ]);

        $errors = [];
        $warnings = [];
        $ipOwners = [];

        /** @var TopologyDevice $device */
        foreach ($topology->topologyDevices as $device) {
            if ($device->interfaces->isEmpty()) {
                $warnings[] = "Device {$device->hostname} has no interfaces defined.";
            }

            $errors = array_merge($errors, $this->validateVlans($device));

            foreach ($device->interfaces as $interface) {
                $name = trim((string) $interface->name);
                if ($name === '') {
                    $errors[] = "Device {$device->hostname} has an interface with missing name.";
                    continue;
                }

                $ip = trim((string) ($interface->ip_address ?? ''));
                $mask = trim((string) ($interface->subnet_mask ?? ''));
                $mode = strtolower((string) ($interface->mode ?? 'routed'));
                $needsIp = in_array($mode, ['routed', 'layer3'], true) || $device->device_type === 'router';

                if ($needsIp && ($ip === '' || $mask === '')) {
                    $errors[] = "Device {$device->hostname} interface {$name} is missing IP or subnet mask.";
                }

                if ($ip !== '' && ! filter_var($ip, FILTER_VALIDATE_IP)) {
                    $errors[] = "Device {$device->hostname} interface {$name} has invalid IP address: {$ip}.";
                }

                if ($mask !== '' && ! $this->isValidSubnetMask($mask)) {
                    $errors[] = "Device {$device->hostname} interface {$name} has invalid subnet mask: {$mask}.";
                }

                if ($ip !== '') {
                    $ipKey = strtolower($ip);
                    if (isset($ipOwners[$ipKey])) {
                        $errors[] = "Duplicate IP {$ip} found on {$device->hostname}/{$name} and {$ipOwners[$ipKey]}.";
                    } else {
                        $ipOwners[$ipKey] = "{$device->hostname}/{$name}";
                    }
                }

                if ($interface->vlan_id !== null && ! $this->isValidVlanId((int) $interface->vlan_id)) {
                    $errors[] = "Device {$device->hostname} interface {$name} has invalid VLAN {$interface->vlan_id}.";
                }

                if ($interface->native_vlan !== null && ! $this->isValidVlanId((int) $interface->native_vlan)) {
                    $errors[] = "Device {$device->hostname} interface {$name} has invalid native VLAN {$interface->native_vlan}.";
                }

                if (! $this->isValidAllowedVlans((string) ($interface->allowed_vlans ?? ''))) {
                    $errors[] = "Device {$device->hostname} interface {$name} has invalid allowed VLAN list.";
                }
            }
        }

        foreach ($topology->topologyLinks as $link) {
            $fromHost = $link->fromDevice?->hostname ?? 'unknown';
            $toHost = $link->toDevice?->hostname ?? 'unknown';
            $fromInt = trim((string) ($link->from_interface_name ?? ''));
            $toInt = trim((string) ($link->to_interface_name ?? ''));

            if ($link->from_topology_device_id === $link->to_topology_device_id) {
                $errors[] = "Invalid link: {$fromHost} cannot connect to itself.";
            }

            if ($fromInt === '' || $toInt === '') {
                $errors[] = "Link {$fromHost} -> {$toHost} has missing connected interface names.";
                continue;
            }

            if (! $this->deviceHasInterface($link->fromDevice?->interfaces ?? collect(), $fromInt)) {
                $errors[] = "Link interface {$fromHost}:{$fromInt} does not exist in device interfaces.";
            }

            if (! $this->deviceHasInterface($link->toDevice?->interfaces ?? collect(), $toInt)) {
                $errors[] = "Link interface {$toHost}:{$toInt} does not exist in device interfaces.";
            }

            if ($link->vlan_id !== null && ! $this->isValidVlanId((int) $link->vlan_id)) {
                $errors[] = "Link {$fromHost} -> {$toHost} has invalid VLAN {$link->vlan_id}.";
            }
        }

        return [
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function validateVlans(TopologyDevice $device): array
    {
        $errors = [];
        $vlans = is_array($device->vlans) ? $device->vlans : [];

        foreach ($vlans as $vlan) {
            $id = (int) ($vlan['id'] ?? 0);
            if (! $this->isValidVlanId($id)) {
                $errors[] = "Device {$device->hostname} has invalid VLAN ID {$id}.";
            }
        }

        return $errors;
    }

    private function isValidVlanId(int $id): bool
    {
        return $id >= 1 && $id <= 4094;
    }

    private function isValidSubnetMask(string $mask): bool
    {
        $validMasks = [
            '255.0.0.0', '255.128.0.0', '255.192.0.0', '255.224.0.0', '255.240.0.0', '255.248.0.0', '255.252.0.0', '255.254.0.0',
            '255.255.0.0', '255.255.128.0', '255.255.192.0', '255.255.224.0', '255.255.240.0', '255.255.248.0', '255.255.252.0', '255.255.254.0',
            '255.255.255.0', '255.255.255.128', '255.255.255.192', '255.255.255.224', '255.255.255.240', '255.255.255.248', '255.255.255.252', '255.255.255.254',
        ];

        return in_array($mask, $validMasks, true);
    }

    private function isValidAllowedVlans(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return true;
        }

        foreach (explode(',', $value) as $part) {
            $chunk = trim($part);
            if ($chunk === '') {
                return false;
            }

            if (str_contains($chunk, '-')) {
                [$start, $end] = array_pad(explode('-', $chunk, 2), 2, null);
                if (! ctype_digit((string) $start) || ! ctype_digit((string) $end)) {
                    return false;
                }
                $startInt = (int) $start;
                $endInt = (int) $end;
                if (! $this->isValidVlanId($startInt) || ! $this->isValidVlanId($endInt) || $startInt > $endInt) {
                    return false;
                }
                continue;
            }

            if (! ctype_digit($chunk) || ! $this->isValidVlanId((int) $chunk)) {
                return false;
            }
        }

        return true;
    }

    private function deviceHasInterface(Collection $interfaces, string $name): bool
    {
        return $interfaces->contains(function ($interface) use ($name): bool {
            return strtolower((string) $interface->name) === strtolower($name);
        });
    }
}
