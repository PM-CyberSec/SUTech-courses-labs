<?php

namespace App\Services\Ansible;

use App\Models\Deployment;
use App\Models\ConfigTemplate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ConfigRenderService
{
    private const DEFAULT_RENDERED_DIR = 'app/ansible/rendered';
    private const TEMPLATES_DIR = 'ansible/templates';

    public function renderForDeployment(Deployment $deployment): string
    {
        if ($deployment->config_template_id) {
            return $this->renderFromTemplate($deployment);
        }

        return $this->renderFromPlaybook($deployment);
    }

    private function renderFromTemplate(Deployment $deployment): string
    {
        $template = ConfigTemplate::query()->find($deployment->config_template_id);

        if (! $template) {
            throw new \RuntimeException('Config template not found');
        }

        $variables = $deployment->variables ?? [];

        $config = $template->template_content;

        foreach ($variables as $key => $value) {
            $stringValue = is_array($value) ? json_encode($value) : (string) $value;
            $config = str_replace('{{ ' . $key . ' }}', $stringValue, $config);
            $config = str_replace('{{' . $key . '}}', $stringValue, $config);
        }

        $deployment->update(['generated_config' => $config]);

        return $this->saveRenderedConfig($deployment, $config);
    }

    private function renderFromPlaybook(Deployment $deployment): string
    {
        $playbook = $deployment->playbook_name ?? 'deployment.yml';

        $templateMap = [
            'vlan_setup.yml' => 'vlan_config.j2',
            'vlan_config.yml' => 'vlan_config.j2',
            'interface_config.yml' => 'interface_config.j2',
            'interface.yml' => 'interface_config.j2',
            'routing_config.yml' => 'routing_config.j2',
            'routing.yml' => 'routing_config.j2',
        ];

        $templateFile = $templateMap[$playbook] ?? null;

        if (! $templateFile) {
            return $deployment->generated_config ?? '';
        }

        $templatePath = base_path(self::TEMPLATES_DIR . '/' . $templateFile);

        if (! file_exists($templatePath)) {
            return $deployment->generated_config ?? '';
        }

        $templateContent = file_get_contents($templatePath);
        $variables = $deployment->variables ?? [];

        $config = $this->renderTemplate($templateContent, $variables);

        $deployment->update(['generated_config' => $config]);

        return $this->saveRenderedConfig($deployment, $config);
    }

    private function renderTemplate(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $template = str_replace('{{ ' . $key . ' }}', (string) $value, $template);
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
            $template = str_replace('${' . $key . '}', (string) $value, $template);
        }

        return $template;
    }

    private function saveRenderedConfig(Deployment $deployment, string $config): string
    {
        $renderedDir = config('app.ansible.rendered_dir', self::DEFAULT_RENDERED_DIR);
        $fullPath = storage_path($renderedDir);

        if (! is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }

        $filename = 'deployment-' . $deployment->id . '-' . time() . '.conf';
        $filePath = $fullPath . '/' . $filename;

        file_put_contents($filePath, $config);

        $deployment->update(['rendered_config_path' => $filePath]);

        return $filePath;
    }

    public function renderTemplatePreview(ConfigTemplate $template, array $variables): string
    {
        $config = $template->template_content;

        foreach ($variables as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_PRETTY_PRINT);
            }
            $config = str_replace('{{ ' . $key . ' }}', (string) $value, $config);
            $config = str_replace('{{' . $key . '}}', (string) $value, $config);
        }

        return $config;
    }

    public function renderCiscoConfig(array $configData): string
    {
        $output = [];

        if (! empty($configData['hostname'])) {
            $output[] = 'hostname ' . $configData['hostname'];
        }

        if (! empty($configData['vlans'])) {
            foreach ($configData['vlans'] as $vlan) {
                $output[] = 'vlan ' . $vlan['id'];
                $output[] = ' name ' . ($vlan['name'] ?? 'VLAN' . $vlan['id']);
            }
        }

        if (! empty($configData['interfaces'])) {
            foreach ($configData['interfaces'] as $interface) {
                $output[] = 'interface ' . $interface['name'];

                if (! empty($interface['description'])) {
                    $output[] = ' description ' . $interface['description'];
                }

                if (! empty($interface['ip_address'])) {
                    $output[] = ' ip address ' . $interface['ip_address'] . ' ' . ($interface['subnet_mask'] ?? '255.255.255.0');
                }

                if (! empty($interface['vlan'])) {
                    $output[] = ' switchport mode access';
                    $output[] = ' switchport access vlan ' . $interface['vlan'];
                }

                if (! empty($interface['trunk'])) {
                    $output[] = ' switchport mode trunk';
                    if (! empty($interface['allowed_vlans'])) {
                        $output[] = ' switchport trunk allowed vlan ' . $interface['allowed_vlans'];
                    }
                }

                if (($interface['enabled'] ?? true) === true) {
                    $output[] = ' no shutdown';
                }
            }
        }

        if (! empty($configData['routing'])) {
            foreach ($configData['routing'] as $route) {
                if ($route['type'] === 'static') {
                    $output[] = 'ip route ' . $route['network'] . ' ' . $route['mask'] . ' ' . $route['next_hop'];
                } elseif ($route['type'] === 'ospf') {
                    $output[] = 'router ospf ' . ($route['process_id'] ?? 1);
                    $output[] = ' network ' . $route['network'] . ' ' . $route['wildcard'] . ' area ' . ($route['area'] ?? 0);
                } elseif ($route['type'] === 'eigrp') {
                    $output[] = 'router eigrp ' . ($route['asn'] ?? 1);
                    $output[] = ' network ' . $route['network'] . ' ' . $route['wildcard'];
                }
            }
        }

        if (! empty($configData['dhcp_pools'])) {
            foreach ($configData['dhcp_pools'] as $pool) {
                $output[] = 'ip dhcp pool ' . $pool['name'];
                $output[] = ' network ' . $pool['network'] . ' ' . $pool['mask'];
                if (! empty($pool['default_router'])) {
                    $output[] = ' default-router ' . $pool['default_router'];
                }
                if (! empty($pool['dns_server'])) {
                    $output[] = ' dns-server ' . $pool['dns_server'];
                }
                $output[] = '!';
            }
        }

        return implode("\n", $output);
    }
}