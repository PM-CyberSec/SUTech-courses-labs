<?php

namespace App\Services\Ansible;

use App\Models\Deployment;
use App\Models\Device;
use App\Models\Topology;
use App\Models\TopologyDevice;
use App\Models\TopologyLink;
use Illuminate\Support\Facades\DB;

class ValidationService
{
    public function validateDeployment(Deployment $deployment): array
    {
        $errors = [];
        $warnings = [];

        if (! $deployment->device_id) {
            $errors[] = 'No device selected for deployment';
        } else {
            $deviceValidation = $this->validateDevice($deployment->device);
            $errors = array_merge($errors, $deviceValidation['errors']);
            $warnings = array_merge($warnings, $deviceValidation['warnings']);
        }

        if (! $deployment->playbook_name && ! $deployment->config_template_id) {
            $errors[] = 'No playbook or template specified';
        }

        if ($deployment->variables) {
            $varsValidation = $this->validateVariables($deployment->variables, $deployment->playbook_name);
            $errors = array_merge($errors, $varsValidation['errors']);
            $warnings = array_merge($warnings, $varsValidation['warnings']);
        }

        $ipConflictValidation = $this->checkIpConflicts($deployment);
        $errors = array_merge($errors, $ipConflictValidation['errors']);
        $warnings = array_merge($warnings, $ipConflictValidation['warnings']);

        $vlanConflictValidation = $this->checkVlanConflicts($deployment);
        $errors = array_merge($errors, $vlanConflictValidation['errors']);
        $warnings = array_merge($warnings, $vlanConflictValidation['warnings']);

        return [
            'passed' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'validated_at' => now()->toIso8601String(),
        ];
    }

    public function validateTopology(Topology $topology): array
    {
        $errors = [];
        $warnings = [];

        $devices = $topology->topologyDevices()->with('interfaces')->get();
        $links = $topology->topologyLinks()->get();

        if ($devices->isEmpty()) {
            $errors[] = 'Topology must have at least one device';
        }

        foreach ($devices as $device) {
            $deviceErrors = $this->validateTopologyDevice($device);
            $errors = array_merge($errors, $deviceErrors['errors']);
            $warnings = array_merge($warnings, $deviceErrors['warnings']);
        }

        $ipValidation = $this->validateTopologyIpPlan($devices);
        $errors = array_merge($errors, $ipValidation['errors']);
        $warnings = array_merge($warnings, $ipValidation['warnings']);

        $vlanValidation = $this->validateTopologyVlanPlan($topology);
        $errors = array_merge($errors, $vlanValidation['errors']);
        $warnings = array_merge($warnings, $vlanValidation['warnings']);

        $linkValidation = $this->validateTopologyLinks($devices, $links);
        $errors = array_merge($errors, $linkValidation['errors']);
        $warnings = array_merge($warnings, $linkValidation['warnings']);

        return [
            'passed' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'validated_at' => now()->toIso8601String(),
        ];
    }

    private function validateDevice(?Device $device): array
    {
        $errors = [];
        $warnings = [];

        if (! $device) {
            $errors[] = 'Device not found';
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        if (empty($device->hostname)) {
            $errors[] = 'Device hostname is required';
        }

        if (empty($device->mgmt_ip) && empty($device->ansible_host)) {
            $errors[] = 'Management IP or Ansible host is required';
        }

        if ($device->mgmt_ip && ! $this->isValidIp($device->mgmt_ip)) {
            $errors[] = 'Invalid management IP address: ' . $device->mgmt_ip;
        }

        if (empty($device->auth_username)) {
            $errors[] = 'Authentication username is required';
        }

        if ($device->status === 'offline') {
            $warnings[] = 'Device is currently offline';
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    private function validateVariables(array $variables, ?string $playbook): array
    {
        $errors = [];
        $warnings = [];

        $vlanPlaybooks = ['vlan_setup.yml', 'vlan_config.yml'];
        if (in_array($playbook, $vlanPlaybooks)) {
            if (isset($variables['vlan_id'])) {
                if ($variables['vlan_id'] < 1 || $variables['vlan_id'] > 4094) {
                    $errors[] = 'VLAN ID must be between 1 and 4094';
                }
            } else {
                $errors[] = 'VLAN ID is required for VLAN configuration';
            }
        }

        $interfacePlaybooks = ['interface_config.yml', 'interface.yml'];
        if (in_array($playbook, $interfacePlaybooks)) {
            if (empty($variables['interface_name'])) {
                $errors[] = 'Interface name is required';
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    private function checkIpConflicts(Deployment $deployment): array
    {
        $errors = [];
        $warnings = [];

        if (! $deployment->device) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $mgmtIp = $deployment->device->mgmt_ip;
        if (! $mgmtIp) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $conflictingDevices = Device::query()
            ->where('id', '!=', $deployment->device_id)
            ->where('mgmt_ip', $mgmtIp)
            ->count();

        if ($conflictingDevices > 0) {
            $errors[] = "IP address conflict: {$mgmtIp} is already in use";
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    private function checkVlanConflicts(Deployment $deployment): array
    {
        $errors = [];
        $warnings = [];

        $variables = $deployment->variables ?? [];
        $vlanId = $variables['vlan_id'] ?? null;

        if (! $vlanId) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    private function validateTopologyDevice(TopologyDevice $device): array
    {
        $errors = [];
        $warnings = [];

        if (empty($device->name)) {
            $errors[] = 'Device name is required';
        }

        if (empty($device->device_type)) {
            $errors[] = 'Device type is required for: ' . ($device->name ?? 'unknown');
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    private function validateTopologyIpPlan($devices): array
    {
        $errors = [];
        $warnings = [];

        $usedIps = [];

        foreach ($devices as $device) {
            $interfaces = $device->interfaces ?? [];

            foreach ($interfaces as $interface) {
                $ip = $interface['ip_address'] ?? null;
                if (! $ip) {
                    continue;
                }

                if (! $this->isValidIp($ip)) {
                    $errors[] = "Invalid IP address {$ip} on device {$device->name}";
                    continue;
                }

                if (isset($usedIps[$ip])) {
                    $errors[] = "IP conflict: {$ip} is used by both {$usedIps[$ip]} and {$device->name}";
                } else {
                    $usedIps[$ip] = $device->name;
                }

                $gateway = $interface['gateway'] ?? null;
                if ($gateway && ! $this->isValidIp($gateway)) {
                    $errors[] = "Invalid gateway {$gateway} on device {$device->name}";
                }
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    private function validateTopologyVlanPlan(Topology $topology): array
    {
        $errors = [];
        $warnings = [];

        $metadata = $topology->metadata ?? [];
        $vlans = $metadata['vlans'] ?? [];
        $usedVlanIds = [];

        foreach ($vlans as $vlan) {
            $id = $vlan['id'] ?? null;
            if (! $id) {
                continue;
            }

            if ($id < 1 || $id > 4094) {
                $errors[] = "VLAN ID {$id} is out of valid range (1-4094)";
            }

            if (isset($usedVlanIds[$id])) {
                $errors[] = "Duplicate VLAN ID {$id}";
            } else {
                $usedVlanIds[$id] = true;
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    private function validateTopologyLinks($devices, $links): array
    {
        $errors = [];
        $warnings = [];

        $deviceNames = $devices->pluck('name')->toArray();

        foreach ($links as $link) {
            if (! in_array($link->source_device, $deviceNames)) {
                $errors[] = "Link references unknown source device: {$link->source_device}";
            }

            if (! in_array($link->target_device, $deviceNames)) {
                $errors[] = "Link references unknown target device: {$link->target_device}";
            }

            if ($link->source_device === $link->target_device) {
                $errors[] = 'Link cannot connect a device to itself';
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    private function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
}