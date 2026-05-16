<?php

namespace App\Services\Cisco;

class AclConfigService
{
    /**
     * @param  array<int, array<string, mixed>>  $acls
     * @return array<int, string>
     */
    public function build(array $acls): array
    {
        $lines = [];

        foreach ($acls as $acl) {
            if (empty($acl['number']) || empty($acl['action']) || empty($acl['source'])) {
                continue;
            }

            $protocol = strtolower((string) ($acl['protocol'] ?? 'ip'));
            $destination = trim((string) ($acl['destination'] ?? ''));
            $port = trim((string) ($acl['port'] ?? ''));
            $line = "access-list {$acl['number']} {$acl['action']} {$protocol} {$acl['source']}";

            if ($destination !== '') {
                $line .= " {$destination}";
            }

            if ($port !== '') {
                $line .= " eq {$port}";
            }

            $lines[] = trim($line);
        }

        return $lines;
    }
}