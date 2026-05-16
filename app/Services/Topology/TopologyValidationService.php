<?php

namespace App\Services\Topology;

use App\Models\TopologyInterface;
use App\Models\TopologyDevice;

class TopologyValidationService
{
    /**
     * Validate a topology draft and fix missing interfaces.
     *
     * @param  array  $topology  The topology draft with devices and links
     * @param  array  $vlans  VLAN plan
     * @param  array  $ipPlan  IP plan
     * @param  array  $routingPlan  Routing plan
     * @param  bool  $autoRepair  Whether to auto-create missing interfaces
     * @return array{errors: array, warnings: array, repaired_interfaces: array}
     */
    public function validate(array $topology, array $vlans, array $ipPlan, array $routingPlan, bool $autoRepair = true): array
    {
        $errors = [];
        $warnings = [];
        $repairedInterfaces = [];

        $ipOwners = [];
        $deviceInterfaceMap = $this->buildDeviceInterfaceMap($topology);

        // Phase 1: Validate devices and their interfaces
        foreach ($topology['devices'] as $device) {
            if (empty($device['interfaces'])) {
                $warnings[] = $device['name'] . ' has no interfaces defined.';
            } else {
                foreach ($device['interfaces'] as $interface) {
                    $ip = (string) ($interface['ip_address'] ?? '');
                    if ($ip !== '') {
                        if (isset($ipOwners[$ip])) {
                            $errors[] = 'Duplicate IP ' . $ip . ' detected on ' . $device['name'] . ' and ' . $ipOwners[$ip] . '.';
                        } else {
                            $ipOwners[$ip] = $device['name'];
                        }
                    }

                    if (($interface['mode'] ?? '') === 'trunk' && empty($interface['allowed_vlans'])) {
                        $warnings[] = $device['name'] . ' trunk interface ' . $interface['name'] . ' does not specify allowed VLANs.';
                    }
                }
            }
        }

        // Phase 2: Validate links reference existing interfaces
        foreach ($topology['links'] as $link) {
            $sourceDevice = $link['source_device'] ?? '';
            $sourceInterface = $link['source_interface'] ?? '';
            $targetDevice = $link['target_device'] ?? '';
            $targetInterface = $link['target_interface'] ?? '';

            // Validate source interface exists
            if (! $this->interfaceExists($deviceInterfaceMap, $sourceDevice, $sourceInterface)) {
                if ($autoRepair) {
                    $repaired = $this->ensureInterfaceExists($topology, $sourceDevice, $sourceInterface);
                    if ($repaired) {
                        $repairedInterfaces[] = ['device' => $sourceDevice, 'interface' => $sourceInterface];
                        $warnings[] = "Auto-repaired: Created interface {$sourceInterface} on device {$sourceDevice}.";
                        $deviceInterfaceMap = $this->buildDeviceInterfaceMap($topology);
                    }
                } else {
                    $errors[] = "Link interface does not exist: {$sourceDevice}:{$sourceInterface}";
                }
            }

            // Validate target interface exists
            if (! $this->interfaceExists($deviceInterfaceMap, $targetDevice, $targetInterface)) {
                if ($autoRepair) {
                    $repaired = $this->ensureInterfaceExists($topology, $targetDevice, $targetInterface);
                    if ($repaired) {
                        $repairedInterfaces[] = ['device' => $targetDevice, 'interface' => $targetInterface];
                        $warnings[] = "Auto-repaired: Created interface {$targetInterface} on device {$targetDevice}.";
                        $deviceInterfaceMap = $this->buildDeviceInterfaceMap($topology);
                    }
                } else {
                    $errors[] = "Link interface does not exist: {$targetDevice}:{$targetInterface}";
                }
            }
        }

        if (($topology['services']['dhcp'] ?? false) && empty($routingPlan['protocol'])) {
            $warnings[] = 'DHCP is enabled but no routing protocol was selected.';
        }

        if (($topology['services']['ssh'] ?? false) === true) {
            if (empty($topology['security']['ssh_username']) || empty($topology['security']['ssh_domain'])) {
                $errors[] = 'SSH requires username and domain name.';
            }
        }

        foreach ($vlans as $vlan) {
            if (empty($vlan['id']) || empty($vlan['name'])) {
                $errors[] = 'Each VLAN must have an id and name.';
            }
        }

        return [
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'repaired_interfaces' => $repairedInterfaces,
        ];
    }

    /**
     * Build a map of device names to their interfaces for quick lookup.
     *
     * @param  array  $topology
     * @return array<string, array<string, array>>
     */
    private function buildDeviceInterfaceMap(array $topology): array
    {
        $map = [];
        foreach ($topology['devices'] as $device) {
            $deviceName = $device['name'] ?? $device['hostname'] ?? '';
            if ($deviceName === '') {
                continue;
            }
            $map[$deviceName] = [];
            foreach ($device['interfaces'] ?? [] as $interface) {
                $interfaceName = $interface['name'] ?? '';
                if ($interfaceName !== '') {
                    $map[$deviceName][$interfaceName] = $interface;
                }
            }
        }
        return $map;
    }

    /**
     * Check if an interface exists for a given device.
     *
     * @param  array  $deviceInterfaceMap
     * @param  string  $deviceName
     * @param  string  $interfaceName
     * @return bool
     */
    private function interfaceExists(array $deviceInterfaceMap, string $deviceName, string $interfaceName): bool
    {
        return isset($deviceInterfaceMap[$deviceName][$interfaceName]);
    }

    /**
     * Ensure an interface exists on a device, creating it if necessary.
     *
     * @param  array  $topology
     * @param  string  $deviceName
     * @param  string  $interfaceName
     * @return bool True if interface was created or already exists
     */
    private function ensureInterfaceExists(array &$topology, string $deviceName, string $interfaceName): bool
    {
        // Find the device
        $deviceIndex = null;
        foreach ($topology['devices'] as $index => $device) {
            if (($device['name'] ?? '') === $deviceName) {
                $deviceIndex = $index;
                break;
            }
        }

        if ($deviceIndex === null) {
            return false;
        }

        // Check if interface already exists
        foreach ($topology['devices'][$deviceIndex]['interfaces'] ?? [] as $existing) {
            if (($existing['name'] ?? '') === $interfaceName) {
                return true; // Already exists
            }
        }

        // Create the interface
        $createdInterface = $this->createInterfaceFromName($deviceName, $interfaceName);
        $topology['devices'][$deviceIndex]['interfaces'][] = $createdInterface;

        return true;
    }

    /**
     * Create an interface configuration from a normalized interface name.
     *
     * @param  string  $deviceName
     * @param  string  $interfaceName
     * @return array
     */
    public function createInterfaceFromName(string $deviceName, string $interfaceName): array
    {
        $normalizedName = $this->normalizeInterfaceName($interfaceName);
        $deviceType = $this->inferDeviceType($deviceName);

        // Determine interface type based on naming
        $isTrunk = str_contains(strtolower($interfaceName), '0/24') ||
                   str_contains(strtolower($interfaceName), '0/0');

        $interface = [
            'name' => $normalizedName,
            'type' => $isTrunk ? 'trunk' : 'access',
            'mode' => $isTrunk ? 'trunk' : 'access',
            'status' => 'planned',
        ];

        // Add specific properties based on interface type
        if (str_starts_with($normalizedName, 'GigabitEthernet')) {
            $interface['type'] = 'routed';
            $interface['mode'] = 'routed';
        } elseif (str_starts_with($normalizedName, 'FastEthernet')) {
            // FastEthernet is typically access by default
        } elseif (str_starts_with($normalizedName, 'Ethernet')) {
            $interface['type'] = 'access';
        }

        return $interface;
    }

    /**
     * Normalize interface name to standard format.
     *
     * @param  string  $interfaceName
     * @return string
     */
    public function normalizeInterfaceName(string $interfaceName): string
    {
        // Standardize interface naming
        $interfaceName = trim($interfaceName);

        // Common Cisco interface prefixes
        $patterns = [
            '/^gi(\d)/i' => 'GigabitEthernet$1',
            '/^ge(\d)/i' => 'GigabitEthernet$1',
            '/^fa(\d)/i' => 'FastEthernet$1',
            '/^fe(\d)/i' => 'FastEthernet$1',
            '/^et(\d)/i' => 'Ethernet$1',
            '/^eth(\d)/i' => 'Ethernet$1',
            '/^se(\d)/i' => 'Serial$1',
        ];

        foreach ($patterns as $pattern => $replacement) {
            if (preg_match($pattern, $interfaceName)) {
                return preg_replace($pattern, $replacement, $interfaceName);
            }
        }

        return $interfaceName;
    }

    /**
     * Infer device type from device name.
     *
     * @param  string  $deviceName
     * @return string
     */
    private function inferDeviceType(string $deviceName): string
    {
        $name = strtoupper($deviceName);

        if (str_starts_with($name, 'R')) {
            return 'router';
        }
        if (str_starts_with($name, 'SW')) {
            return 'switch';
        }
        if (str_starts_with($name, 'FW') || str_contains($name, 'ASA')) {
            return 'firewall';
        }
        if (str_starts_with($name, 'SRV') || str_contains($name, 'SERVER')) {
            return 'server';
        }
        if (str_starts_with($name, 'PC')) {
            return 'pc';
        }
        if (str_starts_with($name, 'INET') || str_contains($name, 'CLOUD')) {
            return 'cloud';
        }

        return 'unknown';
    }

    /**
     * Get standard interfaces for a device type.
     *
     * @param  string  $deviceType
     * @param  int  $interfaceCount
     * @return array
     */
    public function getStandardInterfaces(string $deviceType, int $interfaceCount = 4): array
    {
        return match (strtolower($deviceType)) {
            'router' => $this->getRouterStandardInterfaces($interfaceCount),
            'switch' => $this->getSwitchStandardInterfaces($interfaceCount),
            'pc', 'server', 'firewall', 'cloud' => $this->getEndpointStandardInterfaces(),
            default => [],
        };
    }

    /**
     * Get standard router interfaces.
     */
    private function getRouterStandardInterfaces(int $count): array
    {
        $interfaces = [];
        for ($i = 0; $i < min($count, 4); $i++) {
            $interfaces[] = [
                'name' => 'GigabitEthernet0/' . $i,
                'type' => 'routed',
                'mode' => 'routed',
                'status' => 'planned',
            ];
        }
        return $interfaces;
    }

    /**
     * Get standard switch interfaces.
     */
    private function getSwitchStandardInterfaces(int $count): array
    {
        $interfaces = [];
        // Access ports
        for ($i = 1; $i <= min($count, 24); $i++) {
            $interfaces[] = [
                'name' => 'FastEthernet0/' . $i,
                'type' => 'access',
                'mode' => 'access',
                'status' => 'planned',
            ];
        }
        // Add uplink port
        $interfaces[] = [
            'name' => 'FastEthernet0/24',
            'type' => 'trunk',
            'mode' => 'trunk',
            'status' => 'planned',
        ];
        return $interfaces;
    }

    /**
     * Get standard endpoint interfaces (PC, Server, Firewall, Cloud).
     */
    private function getEndpointStandardInterfaces(): array
    {
        return [
            [
                'name' => 'FastEthernet0',
                'type' => 'access',
                'mode' => 'access',
                'status' => 'planned',
            ],
        ];
    }

    /**
     * Validate that links only reference existing interfaces.
     * Returns list of invalid link references.
     *
     * @param  array  $topology
     * @return array
     */
    public function validateLinkInterfaces(array $topology): array
    {
        $deviceInterfaceMap = $this->buildDeviceInterfaceMap($topology);
        $invalidLinks = [];

        foreach ($topology['links'] as $link) {
            $sourceDevice = $link['source_device'] ?? '';
            $sourceInterface = $link['source_interface'] ?? '';
            $targetDevice = $link['target_device'] ?? '';
            $targetInterface = $link['target_interface'] ?? '';

            if (! $this->interfaceExists($deviceInterfaceMap, $sourceDevice, $sourceInterface)) {
                $invalidLinks[] = [
                    'link' => $link,
                    'error' => "Source interface does not exist: {$sourceDevice}:{$sourceInterface}",
                    'device' => $sourceDevice,
                    'interface' => $sourceInterface,
                ];
            }

            if (! $this->interfaceExists($deviceInterfaceMap, $targetDevice, $targetInterface)) {
                $invalidLinks[] = [
                    'link' => $link,
                    'error' => "Target interface does not exist: {$targetDevice}:{$targetInterface}",
                    'device' => $targetDevice,
                    'interface' => $targetInterface,
                ];
            }
        }

        return $invalidLinks;
    }

    /**
     * Check if topology interfaces are properly created.
     *
     * @param  array  $topology
     * @return array{valid: bool, devices_without_interfaces: array, total_interfaces: int}
     */
    public function checkTopologyInterfaces(array $topology): array
    {
        $devicesWithoutInterfaces = [];
        $totalInterfaces = 0;

        foreach ($topology['devices'] as $device) {
            $interfaces = $device['interfaces'] ?? [];
            $interfaceCount = count($interfaces);
            $totalInterfaces += $interfaceCount;

            if ($interfaceCount === 0) {
                $devicesWithoutInterfaces[] = $device['name'] ?? $device['hostname'] ?? 'unknown';
            }
        }

        return [
            'valid' => empty($devicesWithoutInterfaces),
            'devices_without_interfaces' => $devicesWithoutInterfaces,
            'total_interfaces' => $totalInterfaces,
        ];
    }
}