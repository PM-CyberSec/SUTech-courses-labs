<?php

namespace App\Services\Ansible;

use App\Models\Device;
use App\Models\Deployment;
use App\Models\Inventory;
use App\Models\HostVariable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InventoryBuilderService
{
    private const DEFAULT_INVENTORY_DIR = 'ansible/inventory/generated';

    public function generateForDeployment(Deployment $deployment): string
    {
        $inventoryDir = config('app.ansible.inventory_dir', self::DEFAULT_INVENTORY_DIR);
        $fullPath = storage_path($inventoryDir);

        if (! is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }

        $filename = 'deployment-' . $deployment->id . '-' . time() . '.yml';
        $filePath = $fullPath . '/' . $filename;

        $inventoryData = $this->buildInventoryData($deployment);

        file_put_contents($filePath, \Symfony\Component\Yaml\Yaml::dump($inventoryData, 10, 2));

        return $filePath;
    }

    private function buildInventoryData(Deployment $deployment): array
    {
        $device = $deployment->device;

        if (! $device) {
            return ['all' => ['hosts' => []]];
        }

        $inventory = $device->inventory;
        $hostVariables = $device->hostVariables()->get()->keyBy('variable_name');

        $hosts = [];
        $hosts[$device->hostname] = array_filter([
            'ansible_host' => $device->ansible_host ?? $device->mgmt_ip,
            'ansible_port' => $device->ssh_port ?? 22,
            'ansible_user' => $device->auth_username,
            'ansible_network_os' => 'ios',
            'ansible_connection' => $device->connection ?? 'network_cli',
            'device_type' => $device->platform,
            'device_vendor' => $device->vendor,
        ] + $this->serializeHostVariables($hostVariables));

        $groups = [];

        if ($inventory) {
            $groups[$inventory->name] = ['hosts' => [$device->hostname]];
        }

        $deviceRole = $device->metadata['role'] ?? null;
        if ($deviceRole) {
            $groups[$deviceRole] = ['hosts' => [$device->hostname]];
        }

        $platformType = $this->getPlatformGroup($device->platform);
        if ($platformType) {
            $groups[$platformType] = ['hosts' => [$device->hostname]];
        }

        return [
            'all' => [
                'hosts' => $hosts,
                'children' => $groups,
                'vars' => [
                    'ansible_python_interpreter' => 'auto',
                    'ansible_fast_gather' => false,
                ],
            ],
        ];
    }

    public function generateForInventory(Inventory $inventory): string
    {
        $inventoryDir = config('app.ansible.inventory_dir', self::DEFAULT_INVENTORY_DIR);
        $fullPath = storage_path($inventoryDir);

        if (! is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }

        $filename = 'inventory-' . $inventory->id . '-' . time() . '.yml';
        $filePath = $fullPath . '/' . $filename;

        $devices = $inventory->devices()->get();
        $inventoryData = $this->buildInventoryFromDevices($inventory, $devices);

        file_put_contents($filePath, \Symfony\Component\Yaml\Yaml::dump($inventoryData, 10, 2));

        return $filePath;
    }

    private function buildInventoryFromDevices(Inventory $inventory, $devices): array
    {
        $hosts = [];
        $groups = ['all' => ['vars' => []]];

        foreach ($devices as $device) {
            $hostVariables = $device->hostVariables()->get()->keyBy('variable_name');

            $hosts[$device->hostname] = array_filter([
                'ansible_host' => $device->ansible_host ?? $device->mgmt_ip,
                'ansible_port' => $device->ssh_port ?? 22,
                'ansible_user' => $device->auth_username,
                'ansible_network_os' => 'ios',
                'device_type' => $device->platform,
                'device_vendor' => $device->vendor,
            ] + $this->serializeHostVariables($hostVariables));

            $deviceRole = $device->metadata['role'] ?? null;
            if ($deviceRole) {
                if (! isset($groups[$deviceRole])) {
                    $groups[$deviceRole] = ['hosts' => []];
                }
                $groups[$deviceRole]['hosts'][] = $device->hostname;
            }
        }

        $groups['all']['hosts'] = $hosts;

        return $groups;
    }

    private function serializeHostVariables($hostVariables): array
    {
        $serialized = [];
        foreach ($hostVariables as $name => $variable) {
            $value = $variable->variable_value;
            if (is_array($value)) {
                $serialized[$name] = $value;
            } elseif ($variable->is_encrypted && $value) {
                $serialized[$name] = '{{ ' . $name . '_lookup }}';
            } else {
                $serialized[$name] = $value;
            }
        }
        return $serialized;
    }

    private function getPlatformGroup(?string $platform): ?string
    {
        if (! $platform) {
            return null;
        }

        $platform = strtolower($platform);

        if (str_contains($platform, 'switch') || str_contains($platform, 'catalyst')) {
            return 'switches';
        }

        if (str_contains($platform, 'router') || str_contains($platform, 'isr') || str_contains($platform, 'asr')) {
            return 'routers';
        }

        if (str_contains($platform, 'firewall') || str_contains($platform, 'asa')) {
            return 'firewalls';
        }

        return null;
    }

    public function getInventoryContent(Deployment $deployment): array
    {
        return $this->buildInventoryData($deployment);
    }

    public function buildForInventory(Inventory $inventory): string
    {
        return $this->generateForInventory($inventory);
    }
}