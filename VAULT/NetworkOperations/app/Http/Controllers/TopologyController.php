<?php

namespace App\Http\Controllers;

use App\Models\GeneratedConfig;
use App\Models\Topology;
use App\Models\TopologyDevice;
use App\Models\TopologyLink;
use App\Services\Topology\TopologyConfigGeneratorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TopologyController extends Controller
{
    public function __construct(
        private readonly TopologyConfigGeneratorService $topologyConfigGeneratorService
    ) {}

    public function index(Request $request): View
    {
        $topologies = Topology::query()
            ->withCount(['topologyDevices', 'topologyLinks', 'generatedConfigs'])
            ->latest('id')
            ->paginate(12);

        return view('topologies.index', [
            'topologies' => $topologies,
            'currentRole' => $request->attributes->get('resolved_role', session('role', 'viewer')),
        ]);
    }

    public function create(Request $request): View
    {
        return view('topologies.create', [
            'sampleDevicesJson' => $this->sampleDevicesJson(),
            'sampleLinksJson' => $this->sampleLinksJson(),
            'currentRole' => $request->attributes->get('resolved_role', session('role', 'viewer')),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:topologies,name'],
            'description' => ['nullable', 'string'],
            'default_routing_protocol' => ['nullable', 'in:none,static,rip,ospf,eigrp'],
            'devices_json' => ['required', 'string'],
            'links_json' => ['nullable', 'string'],
        ]);

        $devices = $this->decodeJsonArray($data['devices_json'], 'devices_json');
        $links = $this->decodeJsonArray($data['links_json'] ?? '[]', 'links_json');

        if ($devices === []) {
            throw ValidationException::withMessages([
                'devices_json' => 'At least one topology device must be provided.',
            ]);
        }

        $topology = DB::transaction(function () use ($data, $devices, $links): Topology {
            $topology = Topology::create([
                'name' => $data['name'],
                'slug' => $this->buildUniqueSlug($data['name']),
                'description' => $data['description'] ?? null,
                'default_routing_protocol' => $data['default_routing_protocol'] ?? null,
                'metadata' => [
                    'created_via' => 'topology_builder',
                ],
            ]);

            $hostMap = [];

            foreach ($devices as $index => $deviceData) {
                if (! is_array($deviceData)) {
                    throw ValidationException::withMessages([
                        'devices_json' => 'Device definition at row '.($index + 1).' is invalid.',
                    ]);
                }

                $hostname = trim((string) ($deviceData['hostname'] ?? ''));
                $deviceType = trim((string) ($deviceData['device_type'] ?? ''));
                if ($hostname === '' || $deviceType === '') {
                    throw ValidationException::withMessages([
                        'devices_json' => 'Each device must include hostname and device_type.',
                    ]);
                }

                if (! in_array($deviceType, ['router', 'switch', 'multilayer_switch'], true)) {
                    throw ValidationException::withMessages([
                        'devices_json' => "Invalid device_type '{$deviceType}' for device {$hostname}.",
                    ]);
                }

                $device = TopologyDevice::create([
                    'topology_id' => $topology->id,
                    'hostname' => $hostname,
                    'device_type' => $deviceType,
                    'enable_secret' => (string) ($deviceData['enable_secret'] ?? 'class'),
                    'console_password' => (string) ($deviceData['console_password'] ?? 'cisco'),
                    'vty_password' => (string) ($deviceData['vty_password'] ?? 'cisco'),
                    'service_password_encryption' => (bool) ($deviceData['service_password_encryption'] ?? true),
                    'routing_protocol' => (string) ($deviceData['routing_protocol'] ?? $topology->default_routing_protocol),
                    'default_gateway' => $deviceData['default_gateway'] ?? null,
                    'vlans' => $this->asArrayOrEmpty($deviceData['vlans'] ?? []),
                    'static_routes' => $this->asArrayOrEmpty($deviceData['static_routes'] ?? []),
                    'dhcp_pools' => $this->asArrayOrEmpty($deviceData['dhcp_pools'] ?? []),
                    'nat_rules' => $this->asArrayOrEmpty($deviceData['nat_rules'] ?? []),
                    'acl_rules' => $this->asArrayOrEmpty($deviceData['acl_rules'] ?? []),
                    'ssh_settings' => $this->asArrayOrEmpty($deviceData['ssh_settings'] ?? ['enabled' => true]),
                    'metadata' => $this->asArrayOrEmpty($deviceData['metadata'] ?? []),
                ]);

                $hostMap[strtolower($hostname)] = $device->id;

                $interfaces = $this->asArrayOrEmpty($deviceData['interfaces'] ?? []);
                foreach ($interfaces as $interfaceData) {
                    if (! is_array($interfaceData) || empty($interfaceData['name'])) {
                        continue;
                    }

                    $device->deviceInterfaces()->create([
                        'name' => (string) $interfaceData['name'],
                        'mode' => (string) ($interfaceData['mode'] ?? 'routed'),
                        'ip_address' => $interfaceData['ip_address'] ?? null,
                        'subnet_mask' => $interfaceData['subnet_mask'] ?? null,
                        'vlan_id' => $interfaceData['vlan_id'] ?? null,
                        'native_vlan' => $interfaceData['native_vlan'] ?? null,
                        'allowed_vlans' => $interfaceData['allowed_vlans'] ?? null,
                        'description' => $interfaceData['description'] ?? null,
                        'is_shutdown' => (bool) ($interfaceData['is_shutdown'] ?? false),
                    ]);
                }
            }

            foreach ($links as $linkData) {
                if (! is_array($linkData)) {
                    continue;
                }

                $fromHostname = strtolower((string) ($linkData['from_device'] ?? ''));
                $toHostname = strtolower((string) ($linkData['to_device'] ?? ''));

                if (! isset($hostMap[$fromHostname], $hostMap[$toHostname])) {
                    throw ValidationException::withMessages([
                        'links_json' => "Link devices '{$fromHostname}' and '{$toHostname}' must exist in devices_json.",
                    ]);
                }

                TopologyLink::create([
                    'topology_id' => $topology->id,
                    'from_topology_device_id' => $hostMap[$fromHostname],
                    'to_topology_device_id' => $hostMap[$toHostname],
                    'from_interface_name' => $linkData['from_interface'] ?? null,
                    'to_interface_name' => $linkData['to_interface'] ?? null,
                    'link_type' => (string) ($linkData['link_type'] ?? 'routed'),
                    'vlan_id' => $linkData['vlan_id'] ?? null,
                    'allowed_vlans' => $linkData['allowed_vlans'] ?? null,
                    'metadata' => $this->asArrayOrEmpty($linkData['metadata'] ?? []),
                ]);
            }

            return $topology;
        });

        return redirect()->route('topologies.show', $topology)->with('success', 'Topology created successfully.');
    }

    public function show(Request $request, Topology $topology): View
    {
        $topology->load([
            'topologyDevices.interfaces',
            'topologyDevices.deviceInterfaces',
            'topologyLinks.fromDevice',
            'topologyLinks.toDevice',
            'generatedConfigs.topologyDevice',
        ]);

        return view('topologies.show', [
            'topology' => $topology,
            'currentRole' => $request->attributes->get('resolved_role', session('role', 'viewer')),
        ]);
    }

    public function generateConfigs(Topology $topology): RedirectResponse
    {
        $result = $this->topologyConfigGeneratorService->generateForTopology($topology);

        if ($result['errors'] !== []) {
            return redirect()
                ->route('topologies.show', $topology)
                ->withErrors($result['errors']);
        }

        $warningCount = count($result['warnings']);
        $message = 'Generated configs for '.count($result['generated']).' devices successfully.';
        if ($warningCount > 0) {
            $message .= " {$warningCount} warnings were detected.";
        }

        return redirect()->route('topologies.show', $topology)->with('success', $message);
    }

    public function downloadConfig(Topology $topology, GeneratedConfig $generatedConfig)
    {
        abort_unless($generatedConfig->topology_id === $topology->id, 404);

        $generatedConfig->loadMissing('topologyDevice');
        $filename = Str::slug($topology->name).'-'.Str::slug((string) $generatedConfig->topologyDevice?->hostname).'.txt';

        return response($generatedConfig->config_text, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @throws ValidationException
     * @return array<int, mixed>
     */
    private function decodeJsonArray(string $value, string $field): array
    {
        if (trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                $field => "Invalid JSON format in {$field}.",
            ]);
        }

        return array_values($decoded);
    }

    /**
     * @param  mixed  $value
     * @return array<int|string, mixed>
     */
    private function asArrayOrEmpty(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function buildUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while (Topology::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function sampleDevicesJson(): string
    {
        return json_encode([
            [
                'hostname' => 'R1',
                'device_type' => 'router',
                'routing_protocol' => 'ospf',
                'enable_secret' => 'class',
                'console_password' => 'cisco',
                'vty_password' => 'cisco',
                'interfaces' => [
                    ['name' => 'GigabitEthernet0/0', 'mode' => 'routed', 'ip_address' => '192.168.1.1', 'subnet_mask' => '255.255.255.0'],
                    ['name' => 'GigabitEthernet0/1', 'mode' => 'routed', 'ip_address' => '10.0.0.1', 'subnet_mask' => '255.255.255.252'],
                ],
                'static_routes' => [
                    ['destination' => '172.16.10.0', 'mask' => '255.255.255.0', 'next_hop' => '10.0.0.2'],
                ],
                'dhcp_pools' => [
                    ['pool_name' => 'LAN1', 'network' => '192.168.1.0', 'mask' => '255.255.255.0', 'default_router' => '192.168.1.1'],
                ],
                'nat_rules' => [
                    'inside_interfaces' => ['GigabitEthernet0/0'],
                    'outside_interfaces' => ['GigabitEthernet0/1'],
                    'dynamic' => ['acl' => 1, 'network' => '192.168.1.0', 'wildcard' => '0.0.0.255', 'overload_interface' => 'GigabitEthernet0/1'],
                ],
                'acl_rules' => [
                    ['number' => 10, 'action' => 'permit', 'source' => '192.168.1.0 0.0.0.255'],
                ],
                'ssh_settings' => ['enabled' => true, 'username' => 'admin', 'password' => 'admin123', 'domain' => 'autolab.local', 'rsa_bits' => 1024],
            ],
            [
                'hostname' => 'SW1',
                'device_type' => 'switch',
                'default_gateway' => '192.168.1.1',
                'vlans' => [
                    ['id' => 10, 'name' => 'SALES'],
                ],
                'interfaces' => [
                    ['name' => 'FastEthernet0/1', 'mode' => 'access', 'vlan_id' => 10],
                    ['name' => 'FastEthernet0/24', 'mode' => 'trunk', 'allowed_vlans' => '10,20'],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function sampleLinksJson(): string
    {
        return json_encode([
            [
                'from_device' => 'R1',
                'from_interface' => 'GigabitEthernet0/0',
                'to_device' => 'SW1',
                'to_interface' => 'FastEthernet0/24',
                'link_type' => 'routed',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
