<?php

namespace App\Services\Smart;

use App\Models\Device;
use App\Models\Deployment;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;

class ValidationService
{
    public function validateForDeployment(Device $device, array $variables = []): array
    {
        $errors = [];
        $warnings = [];

        if (! $device) {
            $errors[] = 'Device not found';
            return ['passed' => false, 'errors' => $errors, 'warnings' => $warnings];
        }

        if (empty($device->hostname)) {
            $errors[] = 'Device hostname is required';
        }

        if (empty($device->mgmt_ip)) {
            $errors[] = 'Management IP is required for deployment';
        } else {
            $ipValidation = $this->validateIpAddress($device->mgmt_ip);
            if (! $ipValidation['valid']) {
                $errors[] = $ipValidation['error'];
            }
        }

        if (empty($device->auth_username)) {
            $errors[] = 'Device authentication username is required';
        }

        $playbook = $variables['playbook_name'] ?? '';
        $varsValidation = $this->validateVariables($playbook, $variables);
        $errors = array_merge($errors, $varsValidation['errors']);
        $warnings = array_merge($warnings, $varsValidation['warnings']);

        $ipConflictValidation = $this->checkIpConflicts($device);
        $errors = array_merge($errors, $ipConflictValidation['errors']);
        $warnings = array_merge($warnings, $ipConflictValidation['warnings']);

        $vlanValidation = $this->checkVlanConflicts($device, $variables);
        $errors = array_merge($errors, $vlanValidation['errors']);
        $warnings = array_merge($warnings, $vlanValidation['warnings']);

        return [
            'passed' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'validated_at' => now()->toIso8601String(),
        ];
    }

    public function validateVariables(string $playbook, array $variables): array
    {
        $errors = [];
        $warnings = [];

        if (in_array($playbook, ['vlan_setup.yml', 'vlan_config.yml'])) {
            if (empty($variables['vlan_id'])) {
                $errors[] = 'VLAN ID is required for VLAN configuration';
            } elseif ($variables['vlan_id'] < 1 || $variables['vlan_id'] > 4094) {
                $errors[] = 'VLAN ID must be between 1 and 4094';
            }

            if (empty($variables['vlan_name'])) {
                $warnings[] = 'VLAN name not specified, will use default';
            }
        }

        if (in_array($playbook, ['interface_config.yml', 'interface.yml'])) {
            if (empty($variables['interface_name'])) {
                $warnings[] = 'Interface name not specified, using default based on device type';
                if (empty($variables['interface_name'])) {
                    $platform = strtolower($variables['device_platform'] ?? '');
                    if (str_contains($platform, 'switch')) {
                        $variables['interface_name'] = 'FastEthernet0/1';
                    } else {
                        $variables['interface_name'] = 'GigabitEthernet0/0';
                    }
                }
            }

            if (! empty($variables['ip_address'])) {
                $ipValidation = $this->validateIpAddress($variables['ip_address']);
                if (! $ipValidation['valid']) {
                    $errors[] = 'Invalid IP address: ' . $ipValidation['error'];
                }
            }
        }

        if (in_array($playbook, ['vlan_setup.yml', 'vlan_config.yml', 'deployment.yml'])) {
            if (! empty($variables['interface_name']) && ! $this->validateInterfaceName($variables['interface_name'])) {
                $warnings[] = 'Interface name format may not be standard: ' . $variables['interface_name'];
            }
        }

        if (in_array($playbook, ['routing_config.yml', 'routing.yml'])) {
            $protocol = $variables['routing_protocol'] ?? 'none';
            if ($protocol === 'none' || empty($protocol)) {
                $warnings[] = 'No routing protocol specified';
            }

            if ($protocol === 'ospf' && empty($variables['ospf_process_id'])) {
                $errors[] = 'OSPF process ID is required';
            }

            if ($protocol === 'eigrp' && empty($variables['eigrp_asn'])) {
                $errors[] = 'EIGRP AS number is required';
            }
        }

        if (in_array($playbook, ['snmp_config.yml', 'snmp.yml'])) {
            if (empty($variables['snmp_community'])) {
                $warnings[] = 'SNMP community not specified, using default "public"';
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    public function checkIpConflicts(Device $device): array
    {
        $errors = [];
        $warnings = [];

        if (! $device->mgmt_ip) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $conflictingDevice = Device::query()
            ->where('id', '!=', $device->id)
            ->where('mgmt_ip', $device->mgmt_ip)
            ->first();

        if ($conflictingDevice) {
            $errors[] = "IP conflict: {$device->mgmt_ip} is already assigned to device '{$conflictingDevice->hostname}'";
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    public function checkVlanConflicts(Device $device, array $variables): array
    {
        $errors = [];
        $warnings = [];

        $vlanId = $variables['vlan_id'] ?? null;
        if (! $vlanId) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    public function validateIpAddress(string $ip): array
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['valid' => false, 'error' => 'Invalid IP address format'];
        }

        $parts = explode('.', $ip);
        if (count($parts) !== 4) {
            return ['valid' => false, 'error' => 'IP address must have 4 octets'];
        }

        foreach ($parts as $part) {
            if ($part < 0 || $part > 255) {
                return ['valid' => false, 'error' => 'IP address octets must be between 0 and 255'];
            }
        }

        if ($parts[0] === 0 || $parts[0] === 255) {
            return ['valid' => false, 'error' => 'Invalid network address'];
        }

        return ['valid' => true, 'error' => null];
    }

    public function validateNetwork(string $network, string $mask): array
    {
        $ipValidation = $this->validateIpAddress($network);
        if (! $ipValidation['valid']) {
            return ['valid' => false, 'error' => $ipValidation['error']];
        }

        $maskValidation = $this->validateIpAddress($mask);
        if (! $maskValidation['valid']) {
            return ['valid' => false, 'error' => $maskValidation['error']];
        }

        $networkParts = explode('.', $network);
        $maskParts = explode('.', $mask);

        $validMasks = [
            '255.255.255.255',
            '255.255.255.254',
            '255.255.255.252',
            '255.255.255.248',
            '255.255.255.240',
            '255.255.255.224',
            '255.255.255.192',
            '255.255.255.128',
            '255.255.255.0',
            '255.255.254.0',
            '255.255.252.0',
            '255.255.248.0',
            '255.255.240.0',
            '255.255.224.0',
            '255.255.192.0',
            '255.255.128.0',
            '255.255.0.0',
            '255.254.0.0',
            '255.252.0.0',
            '255.248.0.0',
            '255.240.0.0',
            '255.224.0.0',
            '255.192.0.0',
            '255.128.0.0',
            '255.0.0.0',
        ];

        $maskStr = implode('.', $maskParts);
        if (! in_array($maskStr, $validMasks)) {
            return ['valid' => false, 'error' => 'Invalid subnet mask'];
        }

        for ($i = 0; $i < 4; $i++) {
            $networkPart = (int) $networkParts[$i];
            $maskPart = (int) $maskParts[$i];
            $networkBits = $networkPart & ~$maskPart;

            if ($networkBits !== 0) {
                return ['valid' => false, 'error' => 'Network address has host bits set'];
            }
        }

        return ['valid' => true, 'error' => null];
    }

    public function validateInterfaceName(string $interfaceName): bool
    {
        $validPatterns = [
            '/^GigabitEthernet\d+\/\d+(\/\d+)?$/',
            '/^FastEthernet\d+\/\d+(\/\d+)?$/',
            '/^Ethernet\d+$/',
            '/^TenGigabitEthernet\d+\/\d+(\/\d+)?$/',
            '/^Loopback\d+$/',
            '/^Vlan\d+$/',
            '/^Port-channel\d+$/',
        ];

        foreach ($validPatterns as $pattern) {
            if (preg_match($pattern, $interfaceName)) {
                return true;
            }
        }

        return false;
    }
}