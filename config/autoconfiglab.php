<?php

return [
    'ansible_playbook_bin' => env('ANSIBLE_PLAYBOOK_BIN', 'ansible-playbook'),
    'ansible_inventory_dir' => base_path(env('ANSIBLE_INVENTORY_DIR', 'ansible/inventory')),
    'ansible_playbook_dir' => base_path(env('ANSIBLE_PLAYBOOK_DIR', 'ansible/playbooks')),
    'ansible_rendered_dir' => storage_path(env('ANSIBLE_RENDERED_DIR', 'app/ansible/rendered')),
    'ansible_logs_dir' => storage_path(env('ANSIBLE_LOGS_DIR', 'app/ansible/logs')),
    'allow_role_header' => (bool) env('AUTOCONFIGLAB_ALLOW_ROLE_HEADER', true),
];
