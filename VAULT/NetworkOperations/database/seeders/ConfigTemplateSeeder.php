<?php

namespace Database\Seeders;

use App\Models\ConfigTemplate;
use Illuminate\Database\Seeder;

class ConfigTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Interface Access Template',
                'slug' => 'interface-access-template',
                'category' => 'interface',
                'template_group' => 'switching',
                'description' => 'Configure an access interface and description.',
                'template_body' => <<<'BLADE'
interface {{ $host['interface_name'] ?? 'GigabitEthernet0/1' }}
 description {{ $host['interface_description'] ?? 'Configured by AutoConfigLab' }}
 switchport mode access
 switchport access vlan {{ $host['access_vlan'] ?? 10 }}
 no shutdown
BLADE,
            ],
            [
                'name' => 'VLAN Template',
                'slug' => 'vlan-template',
                'category' => 'vlan',
                'template_group' => 'switching',
                'description' => 'Create VLAN and assign a name.',
                'template_body' => <<<'BLADE'
vlan {{ $host['vlan_id'] ?? 100 }}
 name {{ $host['vlan_name'] ?? 'AUTO_VLAN' }}
BLADE,
            ],
            [
                'name' => 'Static Route Template',
                'slug' => 'static-route-template',
                'category' => 'routing',
                'template_group' => 'routing',
                'description' => 'Configure a static route.',
                'template_body' => <<<'BLADE'
ip route {{ $host['destination_cidr'] ?? '10.10.10.0 255.255.255.0' }} {{ $host['next_hop'] ?? '192.168.1.1' }}
BLADE,
            ],
            [
                'name' => 'Rollback Snapshot Template',
                'slug' => 'rollback-snapshot-template',
                'category' => 'rollback',
                'template_group' => 'security',
                'description' => 'Template used for rollback snapshots.',
                'template_body' => <<<'BLADE'
! rollback snapshot for {{ $device['hostname'] }}
! generated at {{ now() }}
BLADE,
            ],
        ];

        foreach ($templates as $template) {
            ConfigTemplate::query()->updateOrCreate(
                ['slug' => $template['slug']],
                array_merge($template, ['version' => 1, 'is_active' => true])
            );
        }
    }
}
