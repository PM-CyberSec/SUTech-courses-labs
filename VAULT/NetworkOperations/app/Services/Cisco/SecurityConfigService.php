<?php

namespace App\Services\Cisco;

class SecurityConfigService
{
    /**
     * @param  array<string, mixed>  $management
     * @return array<int, string>
     */
    public function build(array $management): array
    {
        $lines = [];

        if (! empty($management['domain_name'])) {
            $lines[] = 'ip domain-name '.$management['domain_name'];
        }

        if (! empty($management['username'])) {
            $privilege = $management['privilege'] ?? 15;
            $secret = $management['password'] ?? 'ChangeMe123!';
            $lines[] = "username {$management['username']} privilege {$privilege} secret {$secret}";
        }

        if (($management['ssh'] ?? false) === true) {
            $bits = $management['rsa_bits'] ?? 1024;
            $lines[] = "crypto key generate rsa modulus {$bits}";
            $lines[] = 'ip ssh version '.($management['ssh_version'] ?? 2);
            if (isset($management['timeout'])) {
                $lines[] = 'ip ssh time-out '.(int) $management['timeout'];
            }
            if (isset($management['retries'])) {
                $lines[] = 'ip ssh authentication-retries '.(int) $management['retries'];
            }
            $lines[] = 'line vty 0 4';
            $lines[] = ' login local';
            $lines[] = ' transport input ssh';
            $lines[] = ' exit';
        }

        if (($management['aaa_mode'] ?? '') === 'local') {
            $lines[] = 'aaa new-model';
            $lines[] = 'aaa authentication login default local';
            $lines[] = 'line console 0';
            $lines[] = ' login authentication default';
            $lines[] = ' exit';
        }

        if (($management['aaa_mode'] ?? '') === 'tacacs') {
            $server = $management['aaa_server'] ?? '192.168.2.2';
            $key = $management['aaa_key'] ?? 'tacacspa55';
            $lines[] = 'aaa new-model';
            $lines[] = "tacacs-server host {$server}";
            $lines[] = "tacacs-server key {$key}";
            $lines[] = 'aaa authentication login default group tacacs+ local';
        }

        if (($management['aaa_mode'] ?? '') === 'radius') {
            $server = $management['aaa_server'] ?? '192.168.3.2';
            $key = $management['aaa_key'] ?? 'radiuspa55';
            $lines[] = 'aaa new-model';
            $lines[] = "radius-server host {$server}";
            $lines[] = "radius-server key {$key}";
            $lines[] = 'aaa authentication login default group radius local';
        }

        if (! empty($management['ntp_server'])) {
            $lines[] = 'ntp server '.$management['ntp_server'];
            $lines[] = 'ntp update-calendar';
            $lines[] = 'ntp authenticate';
            $lines[] = 'ntp trusted-key 1';
            if (! empty($management['ntp_key'])) {
                $lines[] = 'ntp authentication-key 1 md5 '.$management['ntp_key'];
            }
        }

        if (! empty($management['syslog_server'])) {
            $lines[] = 'service timestamps log datetime msec';
            $lines[] = 'logging host '.$management['syslog_server'];
        }

        return $lines;
    }
}