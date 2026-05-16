<?php

namespace App\Services\Topology;

class AiTopologyParserService
{
    public function presetScenarios(): array
    {
        return [
            'basic_lan' => [
                'label' => 'Basic LAN',
                'description' => '1 switch, 4 PCs, single subnet',
                'prompt' => 'Create a basic LAN with 1 switch and 4 PCs on a single subnet.',
            ],
            'vlan_lab' => [
                'label' => 'VLAN Lab',
                'description' => '1 switch with VLAN 10 and VLAN 20',
                'prompt' => 'Create a VLAN lab with VLAN 10 for HR and VLAN 20 for IT.',
            ],
            'router_on_a_stick' => [
                'label' => 'Router-on-a-Stick',
                'description' => '1 router, 1 switch, multiple VLANs',
                'prompt' => 'Create a router-on-a-stick lab with one router, one switch, and VLAN 10 and 20.',
            ],
            'static_routing_lab' => [
                'label' => 'Static Routing Lab',
                'description' => '2 routers and 2 LANs with static routes',
                'prompt' => 'Create a static routing lab with 2 routers and 2 LANs.',
            ],
            'dhcp_dns_http_lab' => [
                'label' => 'DHCP/DNS/HTTP Lab',
                'description' => 'Servers and clients with shared services',
                'prompt' => 'Create a DHCP DNS HTTP lab with a server farm and client PCs.',
            ],
            'secure_enterprise_lab' => [
                'label' => 'Secure Enterprise Lab',
                'description' => '3 routers, OSPF MD5, SSH, NTP, Syslog, ACL',
                'prompt' => 'Create a secure enterprise lab with 3 routers, OSPF MD5, SSH, NTP, Syslog, and ACLs.',
            ],
            'aaa_security_lab' => [
                'label' => 'AAA Security Lab',
                'description' => 'TACACS+, RADIUS, local AAA fallback',
                'prompt' => 'Create an AAA security lab with TACACS+, RADIUS, and local AAA fallback.',
            ],
        ];
    }

    public function parse(string $prompt, ?string $presetKey = null): array
    {
        $presets = $this->presetScenarios();
        $preset = $presetKey && isset($presets[$presetKey]) ? $presets[$presetKey] : null;
        $text = strtolower(trim($prompt));

        $counts = [
            'routers' => $this->extractCount($text, 'router', $presetKey === 'static_routing_lab' ? 2 : 1),
            'switches' => $this->extractCount($text, 'switch', $presetKey === 'basic_lan' ? 1 : 1),
            'pcs' => $this->extractCount($text, 'pc|pcs|computer|client', $presetKey === 'basic_lan' ? 4 : 2),
            'servers' => $this->extractCount($text, 'server', in_array($presetKey, ['dhcp_dns_http_lab', 'aaa_security_lab'], true) ? 3 : 0),
            'firewalls' => $this->extractCount($text, 'firewall|asa', 0),
            'clouds' => $this->extractCount($text, 'cloud|internet', str_contains($text, 'internet') ? 1 : 0),
        ];

        $vlans = $this->extractVlans($prompt, $presetKey);
        if ($vlans === [] && in_array($presetKey, ['vlan_lab', 'router_on_a_stick', 'secure_enterprise_lab'], true)) {
            $vlans = [
                ['id' => 10, 'name' => 'HR'],
                ['id' => 20, 'name' => 'IT'],
            ];
        }

        if ($vlans === []) {
            $vlans = [['id' => 10, 'name' => 'USERS']];
        }

        $routing = $this->detectRouting($text, $presetKey);
        $services = [
            'dhcp' => str_contains($text, 'dhcp') || in_array($presetKey, ['dhcp_dns_http_lab', 'basic_lan'], true),
            'dns' => str_contains($text, 'dns') || in_array($presetKey, ['dhcp_dns_http_lab'], true),
            'http' => str_contains($text, 'http') || in_array($presetKey, ['dhcp_dns_http_lab'], true),
            'nat' => str_contains($text, 'internet') || str_contains($text, 'nat') || in_array($presetKey, ['secure_enterprise_lab'], true),
            'aaa' => str_contains($text, 'aaa') || in_array($presetKey, ['aaa_security_lab'], true),
            'ssh' => str_contains($text, 'ssh') || in_array($presetKey, ['secure_enterprise_lab', 'aaa_security_lab'], true),
            'syslog' => str_contains($text, 'syslog') || in_array($presetKey, ['secure_enterprise_lab'], true),
            'ntp' => str_contains($text, 'ntp') || in_array($presetKey, ['secure_enterprise_lab'], true),
            'ospf_md5' => str_contains($text, 'md5') || str_contains($text, 'ospf md5') || in_array($presetKey, ['secure_enterprise_lab'], true),
        ];

        return [
            'name' => $preset['label'] ?? 'AI Topology',
            'description' => $preset['description'] ?? 'Generated from natural language',
            'scenario_type' => $presetKey ?: $this->guessScenarioType($counts, $services),
            'counts' => $counts,
            'vlans' => $vlans,
            'services' => $services,
            'routing' => $routing,
            'ip_plan' => [
                'lan_subnet' => '192.168.1.0/24',
                'management_vlan' => 99,
                'management_subnet' => '192.168.99.0/24',
                'gateway_octet' => 1,
                'dhcp_start_octet' => 100,
            ],
            'prompt' => $prompt,
            'preset_key' => $presetKey,
        ];
    }

    private function extractCount(string $text, string $needlePattern, int $default): int
    {
        if (preg_match('/(\d+)\s+(?:'.$needlePattern.')\b/i', $text, $matches)) {
            return max(0, (int) $matches[1]);
        }

        return $default;
    }

    private function extractVlans(string $prompt, ?string $presetKey): array
    {
        $vlans = [];
        if (preg_match_all('/vlan\s*(\d+)\s*(?:for|named|name)?\s*([a-z0-9 _-]+)/i', $prompt, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $vlans[] = ['id' => (int) $match[1], 'name' => trim($match[2])];
            }
        }

        if ($vlans === [] && $presetKey === 'vlan_lab') {
            $vlans = [
                ['id' => 10, 'name' => 'HR'],
                ['id' => 20, 'name' => 'IT'],
            ];
        }

        return $vlans;
    }

    private function detectRouting(string $text, ?string $presetKey): array
    {
        if (str_contains($text, 'eigrp')) {
            return ['protocol' => 'eigrp', 'process_id' => 100, 'area' => 0];
        }

        if (str_contains($text, 'rip')) {
            return ['protocol' => 'rip', 'process_id' => 1, 'area' => 0];
        }

        if (str_contains($text, 'static')) {
            return ['protocol' => 'static', 'process_id' => null, 'area' => null];
        }

        if (str_contains($text, 'ospf') || in_array($presetKey, ['router_on_a_stick', 'secure_enterprise_lab', 'static_routing_lab'], true)) {
            return ['protocol' => 'ospf', 'process_id' => 1, 'area' => 0];
        }

        return ['protocol' => 'static', 'process_id' => null, 'area' => null];
    }

    private function guessScenarioType(array $counts, array $services): string
    {
        if (($counts['routers'] ?? 0) >= 3 && ($services['aaa'] ?? false)) {
            return 'aaa_security_lab';
        }

        if (($counts['routers'] ?? 0) >= 3 && ($services['ospf_md5'] ?? false)) {
            return 'secure_enterprise_lab';
        }

        if (($counts['routers'] ?? 0) >= 2) {
            return 'static_routing_lab';
        }

        if (($counts['servers'] ?? 0) >= 1 && (($services['dhcp'] ?? false) || ($services['dns'] ?? false))) {
            return 'dhcp_dns_http_lab';
        }

        if (count($services) > 0) {
            return 'vlan_lab';
        }

        return 'basic_lan';
    }
}